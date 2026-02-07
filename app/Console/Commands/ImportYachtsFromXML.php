<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Yacht;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use XMLReader;
use Illuminate\Support\Facades\Schema;

class ImportYachtsFromXML extends Command
{
    protected $signature = 'yachts:import';
    protected $description = 'Import yachts from the YachtShift XML feed safely, mapping unknown fields automatically with logs';

    public function handle()
    {
        $url = "https://krekelberg.yachtshift.nl/yachtshift/export/feed/key/790b0db72e79d4f9f461b469a6b75c1249";
        $this->info("Fetching XML feed...");

        $response = Http::withHeaders(['User-Agent' => 'Mozilla/5.0'])->get($url);

        if (!$response->ok()) {
            $this->error("Failed to fetch XML feed: {$response->status()}");
            return 1;
        }

        $this->info("Parsing XML feed...");
        $reader = new XMLReader();
        $reader->xml($response->body());

        $count = 0;
        $columns = Schema::getColumnListing('yachts');

        $map = [
            'boat_name' => 'name',           // important: required
            'owner_comments' => 'owners_comment',
            'price_on_demand' => 'price',
            'brand' => 'make',
            'model' => 'model',
            'draught' => 'draft',
            'hull_colour' => 'hull_color',
            'deck_colour' => 'deck_color',
            'vat_situation' => 'vat_status',
            'hull_material' => 'hull_construction',
            'hull_type' => 'hull_shape',
            'vessel_lying' => 'where',
            'Anchor' => 'anchor',
            'Bimini' => 'bimini',
        ];

        $boolFields = [
            'flybridge','oven','microwave','fridge','freezer',
            'air_conditioning','generator','inverter','television',
            'cd_player','dvd_player','anchor','spray_hood','bimini',
            'trailer_included','central_heating','heating','allow_bidding'
        ];

        // Counters for summary
        $summary = [
            'exact' => 0,
            'mapped' => 0,
            'auto_mapped' => 0,
            'skipped' => 0,
            'created_yachts' => 0,
            'updated_yachts' => 0
        ];

        while ($reader->read()) {
            if ($reader->nodeType == XMLReader::ELEMENT && $reader->name === 'advert') {
                $node = $reader->expand();
                if (!$node) continue;

                $doc = new \DOMDocument();
                $docNode = $doc->importNode($node, true);
                $doc->appendChild($docNode);

                $xml = simplexml_import_dom($docNode);
                $data = [];

                foreach ($xml->item as $item) {
                    $name = (string) $item['name'];
                    $value = (string) $item;
                    if (!$name) continue;

                    // Exact match
                    if (in_array($name, $columns)) {
                        $data[$name] = $value;
                        $summary['exact']++;
                        continue;
                    }

                    // Predefined map
                    if (isset($map[$name]) && in_array($map[$name], $columns)) {
                        $data[$map[$name]] = $value;
                        $summary['mapped']++;
                        continue;
                    }

                    // Auto-match using levenshtein
                    $closest = null;
                    $shortest = 10; // max allowed distance
                    foreach ($columns as $col) {
                        $lev = levenshtein($name, $col);
                        if ($lev < $shortest) {
                            $closest = $col;
                            $shortest = $lev;
                        }
                    }

                    if ($closest && $shortest <= 3) {
                        $data[$closest] = $value;
                        $summary['auto_mapped']++;
                    } else {
                        $summary['skipped']++;
                    }
                }

                // Ensure vessel_id
                if (!isset($data['vessel_id'])) {
                    $data['vessel_id'] = $data['external_url'] ?? Str::uuid();
                }

                // Ensure required fields
                $data['name'] = $data['name'] ?? $data['boat_name'] ?? 'Unnamed Yacht';
                $data['status'] = $data['status'] ?? 'Draft';
                $data['allow_bidding'] = $data['allow_bidding'] ?? 0;

                // Normalize booleans
                foreach ($boolFields as $boolField) {
                    if (isset($data[$boolField])) {
                        $data[$boolField] = filter_var($data[$boolField], FILTER_VALIDATE_BOOLEAN);
                    }
                }

                // Safe updateOrCreate: only fill null columns
                $yacht = Yacht::where('vessel_id', $data['vessel_id'])->first();
                if ($yacht) {
                    foreach ($data as $key => $value) {
                        if (is_null($yacht->$key)) {
                            $yacht->$key = $value;
                        }
                    }
                    $yacht->save();
                    $summary['updated_yachts']++;
                } else {
                    Yacht::create($data);
                    $summary['created_yachts']++;
                }

                $count++;
            }
        }

        $reader->close();

        $this->info("Imported/Updated {$count} yachts successfully.");
        $this->info("Summary Report:");
        $this->info("Exact matches: {$summary['exact']}");
        $this->info("Mapped fields: {$summary['mapped']}");
        $this->info("Auto-mapped fields: {$summary['auto_mapped']}");
        $this->info("Skipped fields: {$summary['skipped']}");
        $this->info("New yachts created: {$summary['created_yachts']}");
        $this->info("Existing yachts updated: {$summary['updated_yachts']}");

        return 0;
    }
}
