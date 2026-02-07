<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yachts', function (Blueprint $table) {
            $table->string('external_url')->nullable()->after('reference_code');
            $table->string('print_url')->nullable()->after('external_url');
            $table->text('owners_comment')->nullable()->after('print_url');
            $table->text('reg_details')->nullable()->after('owners_comment');
            $table->text('known_defects')->nullable()->after('reg_details');
            $table->string('last_serviced')->nullable()->after('known_defects');
            $table->integer('passenger_capacity')->nullable()->after('last_serviced');
            $table->string('loa')->nullable()->after('length');
            $table->string('lwl')->nullable()->after('loa');
            $table->string('air_draft')->nullable()->after('lwl');
            $table->string('designer')->nullable()->after('air_draft');
            $table->string('builder')->nullable()->after('designer');
            $table->string('where')->nullable()->after('builder');
            $table->string('hull_construction')->nullable()->after('hull_shape');
            $table->string('super_structure_colour')->nullable()->after('hull_color');
            $table->string('super_structure_construction')->nullable()->after('super_structure_colour');
            $table->string('cockpit_type')->nullable()->after('deck_construction');
            $table->string('control_type')->nullable()->after('cockpit_type');
            $table->boolean('flybridge')->nullable()->after('control_type');
            $table->boolean('oven')->nullable()->after('flybridge');
            $table->boolean('microwave')->nullable()->after('oven');
            $table->boolean('fridge')->nullable()->after('microwave');
            $table->boolean('freezer')->nullable()->after('fridge');
            $table->boolean('air_conditioning')->nullable()->after('freezer');
            $table->string('stern_thruster')->nullable()->after('air_conditioning');
            $table->string('horse_power')->nullable()->after('engine_power');
        });
    }

    public function down(): void
    {
        Schema::table('yachts', function (Blueprint $table) {
            $table->dropColumn([
                'external_url', 'print_url', 'owners_comment', 'reg_details',
                'known_defects', 'last_serviced', 'passenger_capacity', 'loa', 'lwl',
                'air_draft', 'designer', 'builder', 'where', 'hull_construction',
                'super_structure_colour', 'super_structure_construction', 'cockpit_type',
                'control_type', 'flybridge', 'oven', 'microwave', 'fridge', 'freezer',
                'air_conditioning', 'stern_thruster', 'horse_power'
            ]);
        });
    }
};
