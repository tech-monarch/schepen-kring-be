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
    protected $description = 'Import yachts from XML safely, filling missing values with defaults and logging all field mappings';

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

        // Predefined XML -> DB mapping
        $map = [
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
            'boat_name' => 'name',
            'toilet' => 'heads',
            'shower' => 'baths',
        ];

        $boolFields = [
            'flybridge','oven','microwave','fridge','freezer',
            'air_conditioning','generator','inverter','television',
            'cd_player','dvd_player','anchor','spray_hood','bimini',
            'trailer_included','central_heating','heating'
        ];

        $defaultValues = [
            'string' => 'N/A',
            'text' => 'N/A',
            'int' => 0,
            'tinyint' => 0,
            'decimal' => 0.0,
            'enum' => 'Draft',
            'timestamp' => now()
        ];

        $summary = [
            'exact' => 0,
            'mapped' => 0,
            'auto_mapped' => 0,
            'skipped' => 0,
            'defaulted' => 0,
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
                        $data[$name] = $value ?: $defaultValues['string'];
                        $this->info("Exact match: XML '{$name}' -> DB '{$name}' with value '{$data[$name]}'");
                        $summary['exact']++;
                        continue;
                    }

                    // Predefined map
                    if (isset($map[$name]) && in_array($map[$name], $columns)) {
                        $data[$map[$name]] = $value ?: $defaultValues['string'];
                        $this->info("Mapped: XML '{$name}' -> DB '{$map[$name]}' with value '{$data[$map[$name]]}'");
                        $summary['mapped']++;
                        continue;
                    }

                    // Auto-match using levenshtein
                    $closest = null;
                    $shortest = 10;
                    foreach ($columns as $col) {
                        $lev = levenshtein($name, $col);
                        if ($lev < $shortest) {
                            $closest = $col;
                            $shortest = $lev;
                        }
                    }
                    if ($closest && $shortest <= 3) {
                        $data[$closest] = $value ?: $defaultValues['string'];
                        $this->info("Auto-mapped: XML '{$name}' -> DB '{$closest}' with value '{$data[$closest]}' (distance {$shortest})");
                        $summary['auto_mapped']++;
                    } else {
                        $this->warn("Skipped XML field '{$name}' (no suitable DB column found)");
                        $summary['skipped']++;
                    }
                }

                // Ensure required NOT NULL fields
                $data['vessel_id'] = $data['vessel_id'] ?? $data['external_url'] ?? Str::uuid();
                $data['name'] = $data['name'] ?? 'Unnamed Yacht';
                $data['status'] = $data['status'] ?? 'Draft';
                $data['allow_bidding'] = $data['allow_bidding'] ?? 0;
                $data['price'] = $data['price'] ?? 0;

                // Normalize booleans
                foreach ($boolFields as $boolField) {
                    $data[$boolField] = isset($data[$boolField]) ? filter_var($data[$boolField], FILTER_VALIDATE_BOOLEAN) : 0;
                }

                // Fill other DB columns with default values
                foreach ($columns as $col) {
                    if (!isset($data[$col])) {
                        $type = Schema::getColumnType('yachts', $col);
                        $data[$col] = $defaultValues[$type] ?? 'N/A';
                        $this->info("Defaulted field '{$col}' -> '{$data[$col]}'");
                        $summary['defaulted']++;
                    }
                }

                // Update or create yacht
                $yacht = Yacht::where('vessel_id', $data['vessel_id'])->first();
                if ($yacht) {
                    $yacht->update($data);
                    $this->info("Updated yacht with vessel_id {$data['vessel_id']}");
                    $summary['updated_yachts']++;
                } else {
                    Yacht::create($data);
                    $this->info("Created new yacht with vessel_id {$data['vessel_id']}");
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
        $this->info("Defaulted fields: {$summary['defaulted']}");
        $this->info("New yachts created: {$summary['created_yachts']}");
        $this->info("Existing yachts updated: {$summary['updated_yachts']}");

        return 0;
    }
}
