<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
// Add to your database migration
Schema::table('tasks', function (Blueprint $table) {
    // If priority enum doesn't exist yet
    $table->enum('priority', ['Low', 'Medium', 'High', 'Urgent', 'Critical'])->default('Medium');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            //
        });
    }
};
