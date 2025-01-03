<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Project;
use App\Models\User;

class ProjectsSeeder extends Seeder
{
    public function run()
    {
        // Admin or Manager can create projects
        $admin = User::where('email', 'admin@example.com')->first();

        Project::create([
            'name' => 'Project 1',
            'leader_id' => $admin->id,
            'start_date' => now(),
            'end_date' => now()->addMonths(3),
            'description' => 'This is a test project for role-based permissions.',
        ]);

        Project::create([
            'name' => 'Project 2',
            'leader_id' => $admin->id,
            'start_date' => now(),
            'end_date' => now()->addMonths(2),
            'description' => 'Another project for testing.',
        ]);
    }
}
