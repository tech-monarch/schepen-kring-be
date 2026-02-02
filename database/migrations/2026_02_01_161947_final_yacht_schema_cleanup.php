<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yachts', function (Blueprint $table) {
            // 1. Add Vessel ID if missing (Critical for your gallery folders)
            if (!Schema::hasColumn('yachts', 'vessel_id')) {
                $table->string('vessel_id')->unique()->after('id')->nullable();
            }

            // 2. Add Bidding logic if missing
            if (!Schema::hasColumn('yachts', 'allow_bidding')) {
                $table->boolean('allow_bidding')->default(false)->after('status');
            }

            // 3. Ensure all tech specs exist (Checking against your previous file)
            $cols = [
                'make' => 'string', 'model' => 'string', 'beam' => 'string', 
                'draft' => 'string', 'engine_type' => 'string', 'fuel_type' => 'string',
                'fuel_capacity' => 'string', 'water_capacity' => 'string',
                'location' => 'string'
            ];

            foreach ($cols as $col => $type) {
                if (!Schema::hasColumn('yachts', $col)) {
                    $table->string($col)->nullable();
                }
            }

            // 4. Update integer fields to be nullable instead of default 0 
            // (This prevents the 422 error when the frontend sends an empty string)
            if (Schema::hasColumn('yachts', 'cabins')) {
                $table->integer('cabins')->nullable()->change();
            }
            if (Schema::hasColumn('yachts', 'heads')) {
                $table->integer('heads')->nullable()->change();
            }
        });
    }

    public function down(): void { }
};