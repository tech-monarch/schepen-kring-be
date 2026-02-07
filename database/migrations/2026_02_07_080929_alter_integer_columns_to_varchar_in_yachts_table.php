<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yachts', function (Blueprint $table) {
            $table->text('cabins')->nullable()->change();
            $table->text('heads')->nullable()->change();
            $table->text('passenger_capacity')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('yachts', function (Blueprint $table) {
            $table->integer('cabins')->nullable()->change();
            $table->integer('heads')->nullable()->change();
            $table->integer('passenger_capacity')->nullable()->change();
        });
    }
};
