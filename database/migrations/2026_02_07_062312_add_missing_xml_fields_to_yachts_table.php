<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddedMissingXmlColumnsToYachtsTable extends Migration
{
    public function up()
    {
        $fields = [
            'user_id' => 'bigInteger',
            'name' => 'string',
            'price' => 'decimal:15,2',
            'status' => 'string',
            'year' => 'string',
            'length' => 'string',
            'make' => 'string',
            'model' => 'string',
            'beam' => 'string',
            'draft' => 'string',
            'engine_type' => 'string',
            'fuel_type' => 'string',
            'fuel_capacity' => 'string',
            'water_capacity' => 'string',
            'cabins' => 'integer',
            'heads' => 'integer',
            'description' => 'text',
            'location' => 'string',
            'main_image' => 'string',
            'vessel_id' => 'string',
            'allow_bidding' => 'boolean',
            'vat_status' => 'string',
            'reference_code' => 'string',
            'construction_material' => 'string',
            'dimensions' => 'string',
            'berths' => 'string',
            'hull_shape' => 'string',
            'hull_color' => 'string',
            'deck_color' => 'string',
            'clearance' => 'string',
            'displacement' => 'string',
            'steering' => 'string',
            'engine_brand' => 'string',
            'engine_model' => 'string',
            'engine_power' => 'string',
            'engine_hours' => 'string',
            'max_speed' => 'string',
            'fuel_consumption' => 'string',
            'voltage' => 'string',
            'interior_type' => 'string',
            'water_tank' => 'string',
            'water_system' => 'string',
            'navigation_electronics' => 'text',
            'exterior_equipment' => 'text',
            'safety_equipment' => 'text',
            'trailer_included' => 'boolean',
            'external_url' => 'string',
            'print_url' => 'string',
            'owners_comment' => 'text',
            'reg_details' => 'text',
            'known_defects' => 'text',
            'last_serviced' => 'string',
            'passenger_capacity' => 'integer',
            'loa' => 'string',
            'lwl' => 'string',
            'air_draft' => 'string',
            'designer' => 'string',
            'builder' => 'string',
            'where' => 'string',
            'hull_construction' => 'string',
            'super_structure_colour' => 'string',
            'super_structure_construction' => 'string',
            'cockpit_type' => 'string',
            'control_type' => 'string',
            'flybridge' => 'boolean',
            'oven' => 'boolean',
            'microwave' => 'boolean',
            'fridge' => 'boolean',
            'freezer' => 'boolean',
            'air_conditioning' => 'boolean',
            'stern_thruster' => 'string',
            'horse_power' => 'string',
            'generator' => 'boolean',
            'inverter' => 'boolean',
            'television' => 'boolean',
            'cd_player' => 'boolean',
            'dvd_player' => 'boolean',
            'anchor' => 'boolean',
            'spray_hood' => 'boolean',
            'bimini' => 'boolean',
            'fenders' => 'string',
            'hours_counter' => 'string',
            'cruising_speed' => 'string',
            'max_draft' => 'string',
            'min_draft' => 'string',
            'central_heating' => 'boolean',
            'heating' => 'boolean',
            'fuel' => 'string',
        ];

        foreach ($fields as $column => $type) {
            if (!Schema::hasColumn('yachts', $column)) {
                Schema::table('yachts', function (Blueprint $table) use ($column, $type) {
                    switch ($type) {
                        case 'string':
                            $table->string($column)->nullable();
                            break;
                        case 'text':
                            $table->text($column)->nullable();
                            break;
                        case 'integer':
                            $table->integer($column)->nullable();
                            break;
                        case 'boolean':
                            $table->boolean($column)->default(false)->nullable();
                            break;
                        case 'bigInteger':
                            $table->bigInteger($column)->unsigned()->nullable();
                            break;
                        default:
                            // Handle decimals like "decimal:15,2"
                            if (str_starts_with($type, 'decimal')) {
                                [$precision, $scale] = explode(',', substr($type, 8));
                                $table->decimal($column, $precision, $scale)->nullable();
                            }
                            break;
                    }
                });
                echo "Added column '{$column}'\n";
            } else {
                echo "Column '{$column}' already exists, skipping\n";
            }
        }
    }

    public function down()
    {
        $fields = [
            'user_id','name','price','status','year','length','make','model','beam','draft','engine_type',
            'fuel_type','fuel_capacity','water_capacity','cabins','heads','description','location','main_image',
            'vessel_id','allow_bidding','vat_status','reference_code','construction_material','dimensions','berths',
            'hull_shape','hull_color','deck_color','clearance','displacement','steering','engine_brand','engine_model',
            'engine_power','engine_hours','max_speed','fuel_consumption','voltage','interior_type','water_tank',
            'water_system','navigation_electronics','exterior_equipment','safety_equipment','trailer_included',
            'external_url','print_url','owners_comment','reg_details','known_defects','last_serviced','passenger_capacity',
            'loa','lwl','air_draft','designer','builder','where','hull_construction','super_structure_colour',
            'super_structure_construction','cockpit_type','control_type','flybridge','oven','microwave','fridge',
            'freezer','air_conditioning','stern_thruster','horse_power','generator','inverter','television','cd_player',
            'dvd_player','anchor','spray_hood','bimini','fenders','hours_counter','cruising_speed','max_draft',
            'min_draft','central_heating','heating','fuel'
        ];

        foreach ($fields as $column) {
            if (Schema::hasColumn('yachts', $column)) {
                Schema::table('yachts', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                    echo "Dropped column '{$column}'\n";
                });
            } else {
                echo "Column '{$column}' does not exist, skipping drop\n";
            }
        }
    }
}
