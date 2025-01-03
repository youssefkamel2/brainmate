<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TaskNote;
use App\Models\Task;
use App\Models\User;

class TaskNotesSeeder extends Seeder
{
    public function run()
    {
        // Retrieve some tasks and users
        $tasks = Task::all();
        $users = User::all();

        // If there are no tasks or users, exit the seeder
        if ($tasks->isEmpty() || $users->isEmpty()) {
            return;
        }

        // Seed task notes for tasks
        foreach ($tasks as $task) {
            // Pick a random user to assign as the note user_id
            $user_id = $users->random();

            // Create task notes for the task
            TaskNote::create([
                'task_id' => $task->id,
                'user_id' => $user_id->id,
                'description' => 'Note for task ' . $task->id . ' created by ' . $user_id->name,
            ]);

            // Create another note for the same task
            TaskNote::create([
                'task_id' => $task->id,
                'user_id' => $user_id->id,
                'description' => 'Follow-up note for task ' . $task->id . ' created by ' . $user_id->name,
            ]);
        }
    }
}
