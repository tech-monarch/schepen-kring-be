<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yachts', function (Blueprint $table) {
            // Core XML / new fields
            $table->string('external_url')->nullable();
            $table->string('print_url')->nullable();
            $table->text('owners_comment')->nullable();
            $table->text('reg_details')->nullable();
            $table->text('known_defects')->nullable();
            $table->string('last_serviced')->nullable();
            $table->integer('passenger_capacity')->nullable();
            $table->string('loa')->nullable();
            $table->string('lwl')->nullable();
            $table->string('air_draft')->nullable();
            $table->string('designer')->nullable();
            $table->string('builder')->nullable();
            $table->string('where')->nullable();
            $table->string('hull_construction')->nullable();
            $table->string('super_structure_colour')->nullable();
            $table->string('super_structure_construction')->nullable();
            $table->string('deck_construction')->nullable();
            $table->string('cockpit_type')->nullable();
            $table->string('control_type')->nullable();
            $table->boolean('flybridge')->nullable();
            $table->boolean('oven')->nullable();
            $table->boolean('microwave')->nullable();
            $table->boolean('fridge')->nullable();
            $table->boolean('freezer')->nullable();
            $table->boolean('air_conditioning')->nullable();
            $table->string('stern_thruster')->nullable();
            $table->string('horse_power')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('yachts', function (Blueprint $table) {
            $table->dropColumn([
                'external_url', 'print_url', 'owners_comment', 'reg_details',
                'known_defects', 'last_serviced', 'passenger_capacity', 'loa', 'lwl',
                'air_draft', 'designer', 'builder', 'where', 'hull_construction',
                'super_structure_colour', 'super_structure_construction', 'deck_construction',
                'cockpit_type', 'control_type', 'flybridge', 'oven', 'microwave', 'fridge',
                'freezer', 'air_conditioning', 'stern_thruster', 'horse_power'
            ]);
        });
    }
};
