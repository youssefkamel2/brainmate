<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Models\Project;
use App\Models\TaskMember;
use Illuminate\Database\Seeder;

class TaskMembersSeeder extends Seeder
{

    public function run()
    {
        // Example task and user data
        $task = Task::find(1); // You can use any task ID
        $team = Team::find(1); // You can use any team ID
        $project = Project::find(1); // You can use any project ID
        $user = User::find(1); // You can use any user ID

        // Attach members to a task through task_members pivot table
        $task->members()->attach($user->id, [
            'team_id' => $team->id,
            'project_id' => $project->id,
        ]);

        // Add more members to the task if needed
        $user2 = User::find(2); // Second user
        $task->members()->attach($user2->id, [
            'team_id' => $team->id,
            'project_id' => $project->id,
        ]);
    }
}
