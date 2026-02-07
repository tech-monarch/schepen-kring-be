<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFillableFieldsToYachtsTable extends Migration
{
    public function up()
    {
        Schema::table('yachts', function (Blueprint $table) {

            // Fields from your $fillable
            $fields = [
                'user_id','name','price','status','year','length','make','model',
                'beam','draft','engine_type','fuel_type','fuel_capacity','water_capacity',
                'cabins','heads','description','location','main_image','vessel_id','allow_bidding',
                'vat_status','reference_code','construction_material','dimensions','berths',
                'hull_shape','hull_color','deck_color','clearance','displacement','steering',
                'engine_brand','engine_model','engine_power','engine_hours','max_speed',
                'fuel_consumption','voltage','interior_type','water_tank','water_system',
                'navigation_electronics','exterior_equipment','safety_equipment','trailer_included',
                'external_url','print_url','owners_comment','reg_details','known_defects',
                'last_serviced','passenger_capacity','loa','lwl','air_draft','designer','builder',
                'where','hull_construction','super_structure_colour','super_structure_construction',
                'cockpit_type','control_type','flybridge','oven','microwave','fridge','freezer',
                'air_conditioning','stern_thruster','horse_power','generator','inverter','television',
                'cd_player','dvd_player','anchor','spray_hood','bimini','fenders','hours_counter',
                'cruising_speed','max_draft','min_draft','central_heating','heating','fuel'
            ];

            // Define boolean fields
            $booleanFields = [
                'flybridge','oven','microwave','fridge','freezer','air_conditioning',
                'generator','inverter','television','cd_player','dvd_player','anchor',
                'spray_hood','bimini','trailer_included','central_heating','heating'
            ];

            // Loop and add only if column doesn't exist
            foreach ($fields as $field) {
                if (!Schema::hasColumn('yachts', $field)) {
                    if (in_array($field, $booleanFields)) {
                        $table->boolean($field)->default(false)->nullable();
                    } elseif (strpos($field, 'description') !== false || strpos($field, 'comment') !== false || strpos($field, 'details') !== false || strpos($field, 'equipment') !== false) {
                        $table->text($field)->nullable();
                    } elseif (strpos($field, 'capacity') !== false || strpos($field, 'cabins') !== false || strpos($field, 'heads') !== false) {
                        $table->integer($field)->nullable();
                    } elseif ($field === 'price') {
                        $table->decimal('price', 15, 2)->nullable();
                    } else {
                        $table->string($field)->nullable();
                    }

                    echo "Added column '{$field}'\n";
                } else {
                    echo "Column '{$field}' already exists, skipping\n";
                }
            }
        });
    }

    public function down()
    {
        Schema::table('yachts', function (Blueprint $table) {
            $fieldsToDrop = [
                'user_id','name','price','status','year','length','make','model',
                'beam','draft','engine_type','fuel_type','fuel_capacity','water_capacity',
                'cabins','heads','description','location','main_image','vessel_id','allow_bidding',
                'vat_status','reference_code','construction_material','dimensions','berths',
                'hull_shape','hull_color','deck_color','clearance','displacement','steering',
                'engine_brand','engine_model','engine_power','engine_hours','max_speed',
                'fuel_consumption','voltage','interior_type','water_tank','water_system',
                'navigation_electronics','exterior_equipment','safety_equipment','trailer_included',
                'external_url','print_url','owners_comment','reg_details','known_defects',
                'last_serviced','passenger_capacity','loa','lwl','air_draft','designer','builder',
                'where','hull_construction','super_structure_colour','super_structure_construction',
                'cockpit_type','control_type','flybridge','oven','microwave','fridge','freezer',
                'air_conditioning','stern_thruster','horse_power','generator','inverter','television',
                'cd_player','dvd_player','anchor','spray_hood','bimini','fenders','hours_counter',
                'cruising_speed','max_draft','min_draft','central_heating','heating','fuel'
            ];

            foreach ($fieldsToDrop as $field) {
                if (Schema::hasColumn('yachts', $field)) {
                    $table->dropColumn($field);
                    echo "Dropped column '{$field}'\n";
                } else {
                    echo "Column '{$field}' does not exist, skipping drop\n";
                }
            }
        });
    }
}
