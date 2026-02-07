<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yachts', function (Blueprint $table) {
            // Change integer columns to string
            $table->string('cabins')->nullable()->change();
            $table->string('heads')->nullable()->change();
            $table->string('passenger_capacity')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('yachts', function (Blueprint $table) {
            // Rollback to integer if needed
            $table->integer('cabins')->nullable()->change();
            $table->integer('heads')->nullable()->change();
            $table->integer('passenger_capacity')->nullable()->change();
        });
    }
};
