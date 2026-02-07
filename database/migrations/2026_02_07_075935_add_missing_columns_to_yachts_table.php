<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yachts', function (Blueprint $table) {
            // Add missing columns
            $table->string('ballast')->nullable();
            $table->string('bow_thruster')->nullable();
            $table->string('engine_quantity')->nullable();
            $table->string('tankage')->nullable();
            $table->string('litres_per_hour')->nullable();
            $table->string('gearbox')->nullable();
            $table->string('cylinders')->nullable();
            $table->string('propeller_type')->nullable();
            $table->string('engine_location')->nullable();
            $table->string('cooling_system')->nullable();
            $table->string('navigation_lights')->nullable();
            $table->string('compass')->nullable();
            $table->string('depth_instrument')->nullable();
            $table->string('wind_instrument')->nullable();
            $table->string('autopilot')->nullable();
            $table->string('gps')->nullable();
            $table->string('vhf')->nullable();
            $table->string('plotter')->nullable();
            $table->string('speed_instrument')->nullable();
            $table->string('radar')->nullable();
            $table->string('toilet')->nullable();
            $table->string('shower')->nullable();
            $table->string('bath')->nullable();
            $table->string('life_raft')->nullable();
            $table->string('epirb')->nullable();
            $table->string('bilge_pump')->nullable();
            $table->string('mob_system')->nullable();
            $table->string('genoa')->nullable();
            $table->string('spinnaker')->nullable();
            $table->string('tri_sail')->nullable();
            $table->string('storm_jib')->nullable();
            $table->string('main_sail')->nullable();
            $table->string('winches')->nullable();
            $table->string('battery')->nullable();
            $table->string('battery_charger')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('yachts', function (Blueprint $table) {
            $table->dropColumn([
                'ballast', 'bow_thruster', 'engine_quantity', 'tankage', 'litres_per_hour',
                'gearbox', 'cylinders', 'propeller_type', 'engine_location', 'cooling_system',
                'navigation_lights', 'compass', 'depth_instrument', 'wind_instrument', 'autopilot',
                'gps', 'vhf', 'plotter', 'speed_instrument', 'radar',
                'toilet', 'shower', 'bath', 'life_raft', 'epirb',
                'bilge_pump', 'mob_system', 'genoa', 'spinnaker', 'tri_sail',
                'storm_jib', 'main_sail', 'winches', 'battery', 'battery_charger'
            ]);
        });
    }
};
