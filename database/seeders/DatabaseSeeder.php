<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Yacht;
use App\Models\Bid;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Dynamic Permissions
        $permissions = [
            'manage yachts',
            'view yachts',
            'place bids',
            'accept bids',
            'manage tasks',
            'manage users'
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // 2. Create Roles and Assign Permissions
        $superAdmin = Role::create(['name' => 'SuperAdmin']);
        $superAdmin->givePermissionTo(Permission::all());

        $employee = Role::create(['name' => 'Employee']);
        $employee->givePermissionTo(['view yachts', 'manage tasks', 'accept bids']);

        $customer = Role::create(['name' => 'Customer']);
        $customer->givePermissionTo(['view yachts', 'place bids']);

        // 3. Create Sample Users
        $adminUser = User::create([
            'name' => 'Main Admin',
            'email' => 'admin@maritime.com',
            'password' => Hash::make('password123'),
            'role' => 'Admin', // Legacy field for your UI
            'status' => 'Active',
            'access_level' => 'Full'
        ]);
        $adminUser->assignRole($superAdmin);

        $staffUser = User::create([
            'name' => 'John Deckhand',
            'email' => 'staff@maritime.com',
            'password' => Hash::make('password123'),
            'role' => 'Employee',
            'status' => 'Active',
            'access_level' => 'Limited'
        ]);
        $staffUser->assignRole($employee);

        $clientUser = User::create([
            'name' => 'Vince Millionaire',
            'email' => 'client@maritime.com',
            'password' => Hash::make('password123'),
            'role' => 'Customer',
            'status' => 'Active',
            'access_level' => 'None'
        ]);
        $clientUser->assignRole($customer);

        // 4. Create a Sample Yacht & Bid
        $yacht = Yacht::create([
            'vessel_id' => 'Y-772',
            'name' => 'M/Y Sovereign',
            'status' => 'For Bid',
            'price' => 12500000.00,
            'current_bid' => 13000000.00,
            'year' => '2024',
            'length' => '55m'
        ]);

        Bid::create([
            'yacht_id' => $yacht->id,
            'user_id' => $clientUser->id,
            'amount' => 13000000.00,
            'status' => 'active'
        ]);
    }
}