<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // First, clear any existing data (optional, if starting fresh)
        DB::table('page_permissions')->truncate();
        
        // Insert the updated pages with correct page_keys
        $pages = [
            ['page_key' => 'tasks', 'page_name' => 'Tasks Page', 'description' => 'Access to view tasks'],
            ['page_key' => 'assign_tasks', 'page_name' => 'Assign Tasks', 'description' => 'Access to assign tasks'],
            ['page_key' => 'view_users', 'page_name' => 'View Users', 'description' => 'Access to view users'],
            ['page_key' => 'manage_yachts', 'page_name' => 'Manage Yachts', 'description' => 'Access to manage yachts'],
            ['page_key' => 'biddings', 'page_name' => 'Biddings Page', 'description' => 'Access to biddings'],
            ['page_key' => 'blog', 'page_name' => 'Blog Page', 'description' => 'Access to blog management'],
            ['page_key' => 'dashboard', 'page_name' => 'Dashboard', 'description' => 'Access to dashboard'],
            ['page_key' => 'settings', 'page_name' => 'Settings', 'description' => 'Access to settings'],
        ];

        DB::table('page_permissions')->insert($pages);
    }

    public function down(): void
    {
        // Revert to old pages if needed
        DB::table('page_permissions')->truncate();
        
        $oldPages = [
            ['page_key' => 'tasks', 'page_name' => 'Tasks Page', 'description' => 'Access to view tasks'],
            ['page_key' => 'assign_tasks', 'page_name' => 'Assign Tasks', 'description' => 'Access to assign tasks'],
            ['page_key' => 'view_users', 'page_name' => 'View Users', 'description' => 'Access to view users'],
            ['page_key' => 'manage_yachts', 'page_name' => 'Create/Edit Yachts', 'description' => 'Access to manage yachts'],
            ['page_key' => 'biddings', 'page_name' => 'Biddings Page', 'description' => 'Access to biddings'],
            ['page_key' => 'blog', 'page_name' => 'Blog Page', 'description' => 'Access to blog management'],
        ];

        DB::table('page_permissions')->insert($oldPages);
    }
};