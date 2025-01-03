<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Task;
use App\Models\Team;

class TasksSeeder extends Seeder
{
    public function run()
    {
        $team = Team::first();

        if (!$team) {
            $this->command->error('Ensure you have a team available before running the TasksSeeder.');
            return;
        }

        // Seed example tasks
        Task::create([
            'team_id' => $team->id,
            'name' => 'Design Wireframes',
            'description' => 'Create initial wireframes for the application.',
            'priority' => 'high',
            'deadline' => now()->addDays(7),
            'status' => true,
            'tags' => 'design,wireframes',
        ]);

        Task::create([
            'team_id' => $team->id,
            'name' => 'Develop Authentication Module',
            'description' => 'Implement user login, registration, and password reset functionality.',
            'priority' => 'medium',
            'deadline' => now()->addDays(14),
            'status' => true,
            'tags' => 'development,auth',
        ]);

        Task::create([
            'team_id' => $team->id,
            'name' => 'Test API Endpoints',
            'description' => 'Write and execute test cases for all API endpoints.',
            'priority' => 'low',
            'deadline' => now()->addDays(21),
            'status' => false,
            'tags' => 'testing,api',
        ]);
    }
}
