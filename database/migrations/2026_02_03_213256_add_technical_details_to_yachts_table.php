<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up()
{
    Schema::table('yachts', function (Blueprint $table) {
        // General Specs
        $table->string('brand_model')->nullable();
        $table->string('vat_status')->nullable(); // e.g., "VAT Included"
        $table->string('reference_code')->nullable();
        $table->string('construction_material')->nullable();
        $table->string('dimensions')->nullable(); // L x B x D
        $table->string('berths')->nullable();
        $table->string('hull_shape')->nullable();
        $table->string('hull_color')->nullable();
        $table->string('deck_color')->nullable();
        $table->string('clearance')->nullable(); // Doorvaarthoogte
        // $table->string('draft')->nullable(); // Diepgang
        $table->string('displacement')->nullable(); // Waterverplaatsing
        $table->string('steering')->nullable();

        // Engine & Electricity
        $table->string('engine_brand')->nullable();
        $table->string('engine_model')->nullable();
        $table->string('engine_power')->nullable();
        $table->string('engine_type')->nullable(); // Inboard/Outboard
        $table->string('fuel_type')->nullable();
        $table->string('engine_hours')->nullable();
        $table->string('max_speed')->nullable();
        $table->string('fuel_consumption')->nullable();
        $table->string('voltage')->nullable();

        // Accommodation
        $table->string('cabins')->nullable();
        $table->string('interior_type')->nullable();
        $table->string('water_tank')->nullable();
        $table->string('water_system')->nullable();

        // Equipment & Safety
        $table->text('navigation_electronics')->nullable(); // Store as JSON or Text
        $table->text('exterior_equipment')->nullable();
        $table->boolean('trailer_included')->default(false);
        $table->string('safety_equipment')->nullable();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('yachts', function (Blueprint $table) {
            //
        });
    }
};
