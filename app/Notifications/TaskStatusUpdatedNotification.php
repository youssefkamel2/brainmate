<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Bus\Queueable;
use App\Events\NotificationSent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification as BaseNotification;

class TaskStatusUpdatedNotification extends BaseNotification implements ShouldQueue
{
    use Queueable;

    protected $task;
    protected $updatedBy;

    public function __construct(Task $task, User $updatedBy)
    {
        $this->task = $task;
        $this->updatedBy = $updatedBy;
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    /**
     * Handle storing the notification in the database.
     */
    public function toDatabase($notifiable)
    {
        $statusMessage = "Task '{$this->task->name}' is now {$this->task->status_text}";
        
        if ($this->task->status === Task::STATUS_COMPLETED) {
            $completionStatus = $this->task->deadline && $this->task->completed_at
                ? ($this->task->completed_at->lessThanOrEqualTo($this->task->deadline)
                    ? ' (completed on time)'
                    : ' (completed after deadline)')
                : '';
            $statusMessage .= $completionStatus;
        }

        // Create notification using your custom Notification model
        $notification = Notification::create([
            'user_id' => $notifiable->id,
            'message' => $statusMessage,
            'type' => 'info',
            'read' => false,
            'action_url' => null,
            'metadata' => [
                'task_id' => $this->task->id,
                'task_name' => $this->task->name,
                'team_id' => $this->task->team_id,
                'new_status' => $this->task->status_text,
                'updated_by' => [
                    'id' => $this->updatedBy->id,
                    'name' => $this->updatedBy->name,
                ],
                'completed_at' => $this->task->completed_at,
                'is_overdue' => $this->task->is_overdue,
                'type' => 'task_status_update'
            ]
        ]);

        // Broadcast the notification
        event(new NotificationSent($notification));

        return $notification;
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast($notifiable)
    {
        $notification = $this->toDatabase($notifiable);
        
        return [
            'id' => $notification->id,
            'message' => $notification->message,
            'type' => $notification->type,
            'metadata' => $notification->metadata,
            'created_at' => $notification->created_at
        ];
    }
}