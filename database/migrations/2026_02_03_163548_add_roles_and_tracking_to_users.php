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
    Schema::table('users', function (Blueprint $table) {
        // Adding columns with 'Customer' as default [cite: 165]
        $table->string('role')->default('Customer')->after('password');
        $table->string('status')->default('Active')->after('role');
        $table->string('access_level')->default('None')->after('status');
        
        // Tracking details requested previously
        $table->string('registration_ip')->nullable();
        $table->text('user_agent')->nullable();
        $table->timestamp('terms_accepted_at')->nullable();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
