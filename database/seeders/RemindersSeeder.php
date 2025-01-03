<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Reminder;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;

class RemindersSeeder extends Seeder
{

    public function run()
    {
        // Custom reminder for a specific user
        $user = User::find(1); // Assuming user with ID 1
        Reminder::create([
            'user_id' => $user->id, // User ID for custom reminder
            'task_id' => null, // No task for custom reminder
            'reminder_time' => now()->addDays(1), // Example: 1 day later
            'message' => 'Reminder for custom task',
        ]);

        // Normal reminder for all members of a task
        $task = Task::find(1); // Assuming task with ID 1
        Reminder::create([
            'user_id' => null, // No specific user for normal reminder
            'task_id' => $task->id, // Task ID for normal reminder
            'reminder_time' => now()->addDays(2), // Example: 2 days later
            'message' => 'Reminder for all task members',
        ]);
    }

}
