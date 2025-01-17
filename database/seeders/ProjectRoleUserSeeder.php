<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use App\Models\Project;
use App\Models\Team;

class ProjectRoleUserSeeder extends Seeder
{
    public function run()
    {
        // Get the first 3 users
        $users = User::take(3)->get();

        // Get all role IDs
        $roleIds = Role::pluck('id')->toArray();

        // Get all project IDs
        $projectIds = Project::pluck('id')->toArray();

        // Loop through each user
        foreach ($users as $user) {
            // Loop through each project
            foreach ($projectIds as $projectId) {
                // Assign a random role at the project level (team_id = null)
                $randomRoleId = $roleIds[array_rand($roleIds)];
                $user->roles()->attach($randomRoleId, [
                    'project_id' => $projectId,
                    'team_id' => null, // Project-level role
                ]);

                // Get all teams in the project
                $teams = Team::where('project_id', $projectId)->get();

                // Assign roles at the team level
                foreach ($teams as $team) {
                    $randomRoleId = $roleIds[array_rand($roleIds)];
                    $user->roles()->attach($randomRoleId, [
                        'project_id' => $projectId,
                        'team_id' => $team->id, // Team-level role
                    ]);
                }
            }
        }
    }
}