<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAllMissingYachtColumnsSafely extends Migration
{
    public function up()
    {
        Schema::table('yachts', function (Blueprint $table) {

            // Original Fields
            if (!Schema::hasColumn('yachts', 'user_id')) $table->unsignedBigInteger('user_id')->nullable();
            if (!Schema::hasColumn('yachts', 'name')) $table->string('name')->nullable();
            if (!Schema::hasColumn('yachts', 'price')) $table->decimal('price', 15, 2)->nullable();
            if (!Schema::hasColumn('yachts', 'status')) $table->string('status')->nullable();
            if (!Schema::hasColumn('yachts', 'year')) $table->integer('year')->nullable();
            if (!Schema::hasColumn('yachts', 'length')) $table->string('length')->nullable();
            if (!Schema::hasColumn('yachts', 'make')) $table->string('make')->nullable();
            if (!Schema::hasColumn('yachts', 'model')) $table->string('model')->nullable();
            if (!Schema::hasColumn('yachts', 'beam')) $table->string('beam')->nullable();
            if (!Schema::hasColumn('yachts', 'draft')) $table->string('draft')->nullable();
            if (!Schema::hasColumn('yachts', 'engine_type')) $table->string('engine_type')->nullable();
            if (!Schema::hasColumn('yachts', 'fuel_type')) $table->string('fuel_type')->nullable();
            if (!Schema::hasColumn('yachts', 'fuel_capacity')) $table->string('fuel_capacity')->nullable();
            if (!Schema::hasColumn('yachts', 'water_capacity')) $table->string('water_capacity')->nullable();
            if (!Schema::hasColumn('yachts', 'cabins')) $table->integer('cabins')->nullable();
            if (!Schema::hasColumn('yachts', 'heads')) $table->integer('heads')->nullable();
            if (!Schema::hasColumn('yachts', 'description')) $table->text('description')->nullable();
            if (!Schema::hasColumn('yachts', 'location')) $table->string('location')->nullable();
            if (!Schema::hasColumn('yachts', 'main_image')) $table->string('main_image')->nullable();
            if (!Schema::hasColumn('yachts', 'vessel_id')) $table->string('vessel_id')->nullable();
            if (!Schema::hasColumn('yachts', 'allow_bidding')) $table->boolean('allow_bidding')->default(false);

            // New Technical Fields
            $technicalStringFields = [
                'vat_status','reference_code','construction_material','dimensions','berths',
                'hull_shape','hull_color','deck_color','clearance','displacement','steering',
                'engine_brand','engine_model','engine_power','engine_hours','max_speed',
                'fuel_consumption','voltage','interior_type','water_tank','water_system',
                'external_url','print_url','reg_details','known_defects','last_serviced',
                'loa','lwl','air_draft','designer','builder','where',
                'hull_construction','super_structure_colour','super_structure_construction',
                'cockpit_type','control_type','stern_thruster','horse_power','fenders','hours_counter',
                'cruising_speed','max_draft','min_draft','fuel'
            ];

            foreach ($technicalStringFields as $field) {
                if (!Schema::hasColumn('yachts', $field)) {
                    $table->string($field)->nullable();
                }
            }

            // Boolean Fields
            $booleanFields = [
                'flybridge','oven','microwave','fridge','freezer','air_conditioning',
                'generator','inverter','television','cd_player','dvd_player','anchor',
                'spray_hood','bimini','trailer_included','central_heating','heating','allow_bidding'
            ];

            foreach ($booleanFields as $field) {
                if (!Schema::hasColumn('yachts', $field)) {
                    $table->boolean($field)->default(false);
                }
            }

            // Text fields
            $textFields = ['owners_comment','navigation_electronics','exterior_equipment','safety_equipment','description','known_defects','reg_details','last_serviced'];
            foreach ($textFields as $field) {
                if (!Schema::hasColumn('yachts', $field)) {
                    $table->text($field)->nullable();
                }
            }

            // Integer fields
            $integerFields = ['cabins','heads','berths','passenger_capacity','year'];
            foreach ($integerFields as $field) {
                if (!Schema::hasColumn('yachts', $field)) {
                    $table->integer($field)->nullable();
                }
            }

        });
    }

    public function down()
    {
        Schema::table('yachts', function (Blueprint $table) {
            $allFields = [
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

            foreach ($allFields as $field) {
                if (Schema::hasColumn('yachts', $field)) {
                    $table->dropColumn($field);
                }
            }
        });
    }
}
