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
            'phone' => '01284251988',
            'gender' => 'Male', // New: Gender field
            'birthdate' => '1990-01-01', // New: Birthdate field
            'bio' => 'A passionate developer with 10+ years of experience.', // New: Bio field
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
            'phone' => '01284251988',
            'gender' => 'Male', // New: Gender field
            'birthdate' => '1990-01-01', // New: Birthdate field
            'bio' => 'A passionate developer with 10+ years of experience.', // New: Bio field
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