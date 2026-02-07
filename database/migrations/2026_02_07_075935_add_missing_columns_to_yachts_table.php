<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
Schema::table('yachts', function (Blueprint $table) {
    if (!Schema::hasColumn('yachts', 'ballast')) {
        $table->text('ballast')->nullable();
    }
    if (!Schema::hasColumn('yachts', 'bow_thruster')) {
        $table->text('bow_thruster')->nullable();
    }
    if (!Schema::hasColumn('yachts', 'engine_quantity')) {
        $table->text('engine_quantity')->nullable();
    }
    if (!Schema::hasColumn('yachts', 'tankage')) {
        $table->text('tankage')->nullable();
    }
    if (!Schema::hasColumn('yachts', 'litres_per_hour')) {
        $table->text('litres_per_hour')->nullable();
    }
    if (!Schema::hasColumn('yachts', 'gearbox')) {
        $table->text('gearbox')->nullable();
    }
    if (!Schema::hasColumn('yachts', 'cylinders')) {
        $table->text('cylinders')->nullable();
    }
    if (!Schema::hasColumn('yachts', 'propeller_type')) {
        $table->text('propeller_type')->nullable();
    }
    if (!Schema::hasColumn('yachts', 'engine_location')) {
        $table->text('engine_location')->nullable();
    }
    if (!Schema::hasColumn('yachts', 'cooling_system')) {
        $table->text('cooling_system')->nullable();
    }
    if (!Schema::hasColumn('yachts', 'navigation_lights')) {
        $table->text('navigation_lights')->nullable();
    }
    if (!Schema::hasColumn('yachts', 'compass')) {
        $table->text('compass')->nullable();
    }
    if (!Schema::hasColumn('yachts', 'depth_instrument')) {
        $table->text('depth_instrument')->nullable();
    }
    if (!Schema::hasColumn('yachts', 'wind_instrument')) {
        $table->text('wind_instrument')->nullable();
    }
    if (!Schema::hasColumn('yachts', 'autopilot')) {
        $table->text('autopilot')->nullable();
    }
    if (!Schema::hasColumn('yachts', 'gps')) {
        $table->text('gps')->nullable();
    }
    if (!Schema::hasColumn('yachts', 'vhf')) {
        $table->text('vhf')->nullable();
    }
    if (!Schema::hasColumn('yachts', 'plotter')) {
        $table->text('plotter')->nullable();
    }
    if (!Schema::hasColumn('yachts', 'speed_instrument')) {
        $table->text('speed_instrument')->nullable();
    }
    if (!Schema::hasColumn('yachts', 'radar')) {
        $table->text('radar')->nullable();
    }
    if (!Schema::hasColumn('yachts', 'toilet')) {
        $table->text('toilet')->nullable();
    }
    if (!Schema::hasColumn('yachts', 'shower')) {
        $table->text('shower')->nullable();
    }
    if (!Schema::hasColumn('yachts', 'bath')) {
        $table->text('bath')->nullable();
    }
    if (!Schema::hasColumn('yachts', 'life_raft')) {
        $table->text('life_raft')->nullable();
    }
    if (!Schema::hasColumn('yachts', 'epirb')) {
        $table->text('epirb')->nullable();
    }
    if (!Schema::hasColumn('yachts', 'bilge_pump')) {
        $table->text('bilge_pump')->nullable();
    }
    if (!Schema::hasColumn('yachts', 'mob_system')) {
        $table->text('mob_system')->nullable();
    }
    if (!Schema::hasColumn('yachts', 'genoa')) {
        $table->text('genoa')->nullable();
    }
    if (!Schema::hasColumn('yachts', 'spinnaker')) {
        $table->text('spinnaker')->nullable();
    }
    if (!Schema::hasColumn('yachts', 'tri_sail')) {
        $table->text('tri_sail')->nullable();
    }
    if (!Schema::hasColumn('yachts', 'storm_jib')) {
        $table->text('storm_jib')->nullable();
    }
    if (!Schema::hasColumn('yachts', 'main_sail')) {
        $table->text('main_sail')->nullable();
    }
    if (!Schema::hasColumn('yachts', 'winches')) {
        $table->text('winches')->nullable();
    }
    if (!Schema::hasColumn('yachts', 'battery')) {
        $table->text('battery')->nullable();
    }
    if (!Schema::hasColumn('yachts', 'battery_charger')) {
        $table->text('battery_charger')->nullable();
    }
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
