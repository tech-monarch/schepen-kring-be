<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class ActivityLogPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Create the permission
        Permission::create(['name' => 'view activity logs', 'guard_name' => 'web']);
        
        // Optionally assign it to Admin role
        $adminRole = \Spatie\Permission\Models\Role::where('name', 'Admin')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo('view activity logs');
        }
    }
}