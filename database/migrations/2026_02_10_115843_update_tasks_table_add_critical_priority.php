<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Update priority enum
            $table->enum('priority', ['Low', 'Medium', 'High', 'Urgent', 'Critical'])->default('Medium')->change();
            
            // Add user_id for personal tasks tracking
            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->onDelete('cascade');
            
            // Add type field
            $table->enum('type', ['assigned', 'personal'])->default('assigned');
            
            // Add created_by for tracking who created the task
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->enum('priority', ['Low', 'Medium', 'High', 'Urgent'])->default('Medium')->change();
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
            $table->dropColumn('type');
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');
        });
    }
};