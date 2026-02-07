<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Yacht;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use XMLReader;

class ImportYachtsFromXML extends Command
{
    protected $signature = 'yachts:import';
    protected $description = 'Import yachts from the YachtShift XML feed';

    public function handle()
    {
        $url = "https://krekelberg.yachtshift.nl/yachtshift/export/feed/key/790b0db72e79d4f9f461b469a6b75c1249";
        $this->info("Fetching XML feed...");

        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0'
        ])->get($url);

        if (!$response->ok()) {
            $this->error("Failed to fetch XML feed: {$response->status()}");
            return 1;
        }

        $this->info("Parsing XML feed...");

        $reader = new XMLReader();
        $reader->xml($response->body());

        $count = 0;
while ($reader->read()) {
    if ($reader->nodeType == XMLReader::ELEMENT && $reader->name === 'advert') {

        $node = $reader->expand();
        if (!$node) {
            continue; // skip invalid node
        }

        // Create a DOMDocument and import the node properly
        $doc = new \DOMDocument();
        $docNode = $doc->importNode($node, true);
        $doc->appendChild($docNode);

        $xml = simplexml_import_dom($docNode);

        $data = [];

        // Extract all <item name=""> values
        foreach ($xml->item as $item) {
            $name = (string) $item['name'];
            $value = (string) $item;

            if ($name) {
                $data[$name] = $value;
            }
        }

        // Generate vessel_id if missing
        if (!isset($data['vessel_id'])) {
            $data['vessel_id'] = $data['external_url'] ?? \Illuminate\Support\Str::uuid();
        }

        // Map boolean values
        foreach (['flybridge','oven','microwave','fridge','freezer','air_conditioning'] as $boolField) {
            if (isset($data[$boolField])) {
                $data[$boolField] = filter_var($data[$boolField], FILTER_VALIDATE_BOOLEAN);
            }
        }

        // Update or create yacht
        \App\Models\Yacht::updateOrCreate(
            ['vessel_id' => $data['vessel_id']],
            $data
        );

        $count++;
    }
}


        $reader->close();

        $this->info("Imported/Updated {$count} yachts successfully.");
        return 0;
    }
}
