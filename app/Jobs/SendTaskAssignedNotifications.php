<?php

namespace App\Jobs;

use App\Models\Task;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Bus\Queueable;
use App\Events\NotificationSent;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Notifications\TaskAssignedNotification;

class SendTaskAssignedNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;
    protected $task;

    public function __construct(User $user, Task $task)
    {
        $this->user = $user;
        $this->task = $task;
    }

    public function handle()
    {
        // Send email notification
        $this->user->notify(new TaskAssignedNotification($this->task));

        // Send read-only notification
        $notification = Notification::create([
            'user_id' => $this->user->id,
            'message' => "You have been assigned a new task: {$this->task->name}.",
            'type' => 'info',
            'read' => false,
            'action_url' => NULL,
            'metadata' => [
                'task_id' => $this->task->id,
                'task_name' => $this->task->name,
                'team_id' => $this->task->team_id
            ],
        ]);

        // Broadcast the notification
        event(new NotificationSent($notification));
    }
}