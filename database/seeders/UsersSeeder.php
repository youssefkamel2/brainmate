<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    public function run()
    {
        // Retrieve roles by name (ensure you have these roles in your roles table)
        $adminRole = Role::where('name', 'Admin')->first();
        $managerRole = Role::where('name', 'Manager')->first();
        $leaderRole = Role::where('name', 'Leader')->first();
        $memberRole = Role::where('name', 'Member')->first();

        // Create Admin user
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role_id' => $adminRole->id,  // Assigning the Admin role
        ]);

        // Create Manager user
        User::create([
            'name' => 'Manager User',
            'email' => 'manager@example.com',
            'password' => bcrypt('password'),
            'role_id' => $managerRole->id,  // Assigning the Manager role
        ]);

        // Create Leader user
        User::create([
            'name' => 'Leader User',
            'email' => 'leader@example.com',
            'password' => bcrypt('password'),
            'role_id' => $leaderRole->id,  // Assigning the Leader role
        ]);

        // Create Member user
        User::create([
            'name' => 'Member User',
            'email' => 'member@example.com',
            'password' => bcrypt('password'),
            'role_id' => $memberRole->id,  // Assigning the Member role
        ]);
    }
}
