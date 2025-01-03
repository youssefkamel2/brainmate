<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Attachment;
use App\Models\Task;

class AttachmentsSeeder extends Seeder
{
    public function run()
    {
        // Assuming you have some tasks already created, retrieve some of them
        $tasks = Task::all();

        // If there are no tasks in the database, exit the seeder
        if ($tasks->isEmpty()) {
            return;
        }

        // Seed some attachments
        foreach ($tasks as $task) {
            Attachment::create([
                'name' => 'Sample Attachment for Task ' . $task->id,
                'media' => 'path/to/file_' . $task->id . '.pdf', // You can replace this with actual file paths
                'type' => 'document', // You can change this to 'image' or 'video' if needed
                'task_id' => $task->id,
            ]);

            // You can create more attachments per task if needed
            Attachment::create([
                'name' => 'Another Attachment for Task ' . $task->id,
                'media' => 'path/to/file_' . $task->id . '_2.jpg',
                'type' => 'image',
                'task_id' => $task->id,
            ]);
        }
    }
}
