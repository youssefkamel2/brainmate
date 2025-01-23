<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    public function run()
    {
        User::create([
            'name' => 'John Doe',
            'email' => 'member@example.com',
            'password' => Hash::make('password'),
            'avatar' => 'avatars/john.jpg',
            'status' => true,
            'position' => 'Front-end Developer',
            'level' => 'Senior',
            'skills' => 'JavaScript,React,CSS',
            'experience_years' => 5,
        ]);

        User::create([
            'name' => 'Jane Smith',
            'email' => 'jane.smith@example.com',
            'password' => Hash::make('password'),
            'avatar' => 'avatars/jane.jpg',
            'status' => true,
            'position' => 'Data Engineer',
            'level' => 'Mid-level',
            'skills' => 'php,React,python',
            'experience_years' => 3,
        ]);
    }
}