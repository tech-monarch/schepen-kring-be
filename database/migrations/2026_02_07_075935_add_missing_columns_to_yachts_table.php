<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
Schema::table('yachts', function (Blueprint $table) {
    $table->text('ballast')->nullable();
    $table->text('bow_thruster')->nullable();
    $table->text('engine_quantity')->nullable();
    $table->text('tankage')->nullable();
    $table->text('litres_per_hour')->nullable();
    $table->text('gearbox')->nullable();
    $table->text('cylinders')->nullable();
    $table->text('propeller_type')->nullable();
    $table->text('engine_location')->nullable();
    $table->text('cooling_system')->nullable();
    $table->text('navigation_lights')->nullable();
    $table->text('compass')->nullable();
    $table->text('depth_instrument')->nullable();
    $table->text('wind_instrument')->nullable();
    $table->text('autopilot')->nullable();
    $table->text('gps')->nullable();
    $table->text('vhf')->nullable();
    $table->text('plotter')->nullable();
    $table->text('speed_instrument')->nullable();
    $table->text('radar')->nullable();
    $table->text('toilet')->nullable();
    $table->text('shower')->nullable();
    $table->text('bath')->nullable();
    $table->text('life_raft')->nullable();
    $table->text('epirb')->nullable();
    $table->text('bilge_pump')->nullable();
    $table->text('mob_system')->nullable();
    $table->text('genoa')->nullable();
    $table->text('spinnaker')->nullable();
    $table->text('tri_sail')->nullable();
    $table->text('storm_jib')->nullable();
    $table->text('main_sail')->nullable();
    $table->text('winches')->nullable();
    $table->text('battery')->nullable();
    $table->text('battery_charger')->nullable();
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
