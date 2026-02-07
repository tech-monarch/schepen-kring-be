<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yachts', function (Blueprint $table) {
            
            if (!Schema::hasColumn('yachts', 'external_url')) {
                $table->string('external_url')->nullable();
            }

            if (!Schema::hasColumn('yachts', 'print_url')) {
                $table->string('print_url')->nullable();
            }

            if (!Schema::hasColumn('yachts', 'owners_comment')) {
                $table->text('owners_comment')->nullable();
            }

            if (!Schema::hasColumn('yachts', 'reg_details')) {
                $table->text('reg_details')->nullable();
            }

            if (!Schema::hasColumn('yachts', 'known_defects')) {
                $table->text('known_defects')->nullable();
            }

            if (!Schema::hasColumn('yachts', 'last_serviced')) {
                $table->string('last_serviced')->nullable();
            }

            if (!Schema::hasColumn('yachts', 'passenger_capacity')) {
                $table->integer('passenger_capacity')->nullable();
            }

            if (!Schema::hasColumn('yachts', 'loa')) {
                $table->string('loa')->nullable();
            }

            if (!Schema::hasColumn('yachts', 'lwl')) {
                $table->string('lwl')->nullable();
            }

            if (!Schema::hasColumn('yachts', 'air_draft')) {
                $table->string('air_draft')->nullable();
            }

            if (!Schema::hasColumn('yachts', 'designer')) {
                $table->string('designer')->nullable();
            }

            if (!Schema::hasColumn('yachts', 'builder')) {
                $table->string('builder')->nullable();
            }

            if (!Schema::hasColumn('yachts', 'where')) {
                $table->string('where')->nullable();
            }

            if (!Schema::hasColumn('yachts', 'hull_construction')) {
                $table->string('hull_construction')->nullable();
            }

            if (!Schema::hasColumn('yachts', 'super_structure_colour')) {
                $table->string('super_structure_colour')->nullable();
            }

            if (!Schema::hasColumn('yachts', 'super_structure_construction')) {
                $table->string('super_structure_construction')->nullable();
            }

            if (!Schema::hasColumn('yachts', 'deck_construction')) {
                $table->string('deck_construction')->nullable();
            }

            if (!Schema::hasColumn('yachts', 'cockpit_type')) {
                $table->string('cockpit_type')->nullable();
            }

            if (!Schema::hasColumn('yachts', 'control_type')) {
                $table->string('control_type')->nullable();
            }

            if (!Schema::hasColumn('yachts', 'flybridge')) {
                $table->boolean('flybridge')->nullable();
            }

            if (!Schema::hasColumn('yachts', 'oven')) {
                $table->boolean('oven')->nullable();
            }

            if (!Schema::hasColumn('yachts', 'microwave')) {
                $table->boolean('microwave')->nullable();
            }

            if (!Schema::hasColumn('yachts', 'fridge')) {
                $table->boolean('fridge')->nullable();
            }

            if (!Schema::hasColumn('yachts', 'freezer')) {
                $table->boolean('freezer')->nullable();
            }

            if (!Schema::hasColumn('yachts', 'air_conditioning')) {
                $table->boolean('air_conditioning')->nullable();
            }

            if (!Schema::hasColumn('yachts', 'stern_thruster')) {
                $table->string('stern_thruster')->nullable();
            }

            if (!Schema::hasColumn('yachts', 'horse_power')) {
                $table->string('horse_power')->nullable();
            }

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
