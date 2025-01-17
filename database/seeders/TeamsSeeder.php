<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Team;
use App\Models\Project;
use App\Models\User;

class TeamsSeeder extends Seeder
{
    public function run()
    {
        // Retrieve all projects and users
        $projects = Project::all();
        $users = User::all();

        // If there are no projects or users, exit the seeder
        if ($projects->isEmpty() || $users->isEmpty()) {
            return;
        }

        // Seed teams for each project
        foreach ($projects as $project) {
            // Pick a random user to assign as the team creator
            $addedBy = $users->random();

            // Create a team for the project
            Team::create([
                'name' => 'Team for ' . $project->name,
                'project_id' => $project->id,
                'added_by' => $addedBy->id,
            ]);
        }
    }
}
