<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Yacht;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

class ImportYachtsFromJSON extends Command
{
    protected $signature = 'yachts:import-json';
    protected $description = 'Import yachts from boats.json and update DB';

    public function handle()
    {
        $file = __DIR__ . '/boats.json';

        if (!file_exists($file)) {
            $this->error("JSON file not found at {$file}");
            return 1;
        }

        $this->info("Reading local JSON file...");
        $jsonContent = file_get_contents($file);
        $yachtsJson = json_decode($jsonContent, true);

        if (!$yachtsJson) {
            $this->error("Failed to parse JSON file.");
            return 1;
        }

        $count = 0;
        $summary = [
            'created' => 0,
            'updated' => 0,
            'defaulted' => 0,
        ];

        // Boolean fields for proper casting
        $booleanFields = [
            'flybridge','oven','microwave','fridge','freezer','air_conditioning',
            'generator','inverter','television','cd_player','dvd_player','anchor',
            'spray_hood','bimini','central_heating','heating'
        ];

        foreach ($yachtsJson as $item) {
            $data = [];

            $data['vessel_id'] = $item['external_url'] ?? Str::uuid();
            $data['name'] = $item['boat_name'] ?? 'Unnamed Yacht';
            $data['status'] = $item['status'] ?? 'Draft';
            $data['allow_bidding'] = isset($item['allow_bidding']) ? (bool)$item['allow_bidding'] : false;
            $data['price'] = isset($item['price']) ? (float)$item['price'] : 0;
            $data['user_id'] = 1; // fixed

            $mapping = [
                'current_bid' => 'current_bid',
                'year' => 'year',
                'length' => 'length',
                'loa' => 'loa',
                'lwl' => 'lwl',
                'air_draft' => 'air_draft',
                'designer' => 'designer',
                'builder' => 'builder',
                'where' => 'where',
                'main_image' => 'main_image',
                'make' => 'make',
                'model' => 'model',
                'beam' => 'beam',
                'draft' => 'draft',
                'engine_type' => 'engine_type',
                'fuel_type' => 'fuel_type',
                'fuel_capacity' => 'fuel_capacity',
                'water_capacity' => 'water_capacity',
                'cabins' => 'cabins',
                'heads' => 'heads',
                'description' => 'description',
                'location' => 'where',
                'brand_model' => 'brand_model',
                'vat_status' => 'vat_status',
                'reference_code' => 'hull_number',
                'external_url' => 'external_url',
                'print_url' => 'print_url',
                'owners_comment' => 'owners_comment',
                'reg_details' => 'reg_details',
                'known_defects' => 'known_defects',
                'last_serviced' => 'last_serviced',
                'passenger_capacity' => 'passenger_capacity',
                'construction_material' => 'construction_material',
                'dimensions' => 'dimensions',
                'berths' => 'berths',
                'hull_shape' => 'hull_type',
                'hull_construction' => 'hull_construction',
                'hull_color' => 'hull_colour',
                'super_structure_colour' => 'super_structure_colour',
                'super_structure_construction' => 'super_structure_construction',
                'deck_color' => 'deck_colour',
                'deck_construction' => 'deck_construction',
                'cockpit_type' => 'cockpit_type',
                'control_type' => 'control_type',
                'stern_thruster' => 'stern_thruster',
                'horse_power' => 'horse_power',
                'fenders' => 'fenders',
                'hours_counter' => 'hours',
                'cruising_speed' => 'cruising_speed',
                'max_draft' => 'max_draft',
                'min_draft' => 'min_draft',
                'fuel' => 'fuel',
            ];

            foreach ($mapping as $dbCol => $jsonKey) {
                if (isset($item[$jsonKey])) {
                    $value = $item[$jsonKey];

                    if (in_array($dbCol, $booleanFields)) {
                        $data[$dbCol] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    } elseif (in_array($dbCol, ['cabins','heads','passenger_capacity'])) {
                        $data[$dbCol] = is_numeric($value) ? (int)$value : null;
                    } elseif (in_array($dbCol, ['price','current_bid'])) {
                        $data[$dbCol] = is_numeric($value) ? (float)$value : 0;
                    } else {
                        $data[$dbCol] = $value;
                    }
                } else {
                    $data[$dbCol] = null;
                    $summary['defaulted']++;
                }
            }

            // Update or create
            $yacht = Yacht::where('vessel_id', $data['vessel_id'])->first();
            if ($yacht) {
                $yacht->update($data);
                $summary['updated']++;
                $action = 'Updated';
            } else {
                Yacht::create($data);
                $summary['created']++;
                $action = 'Created';
            }

            $count++;

            // Display a clean table of the yacht details in console
            $columns = array_keys($data);
            $values = array_map(fn($v) => $v ?? 'NULL', array_values($data));

            $this->info("{$action} yacht ({$data['name']})");
            $this->table($columns, [$values]);
        }

        $this->info("Imported/Updated {$count} yachts successfully.");
        $this->info("Summary Report:");
        $this->info("New yachts created: {$summary['created']}");
        $this->info("Existing yachts updated: {$summary['updated']}");
        $this->info("Fields left NULL: {$summary['defaulted']}");

        return 0;
    }
}
