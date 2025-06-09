<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Task;
use App\Models\User;
use Illuminate\Notifications\Messages\BroadcastMessage;

class TaskStatusUpdatedNotification extends Notification implements ShouldQueue
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

    public function toArray($notifiable)
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

        return [
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
            'message' => $statusMessage,
            'type' => 'task_status_update',
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Task Status Updated: ' . $this->task->name)
            ->line('The status of the task has been updated.')
            ->line('**Task Details:**')
            ->line('Name: ' . $this->task->name)
            ->line('Description: ' . $this->task->description)
            ->line('Priority: ' . ucfirst($this->task->priority))
            ->line('Deadline: ' . $this->task->deadline)
            ->line('**New Status:** ' . $this->task->status_text)
            ->action('View Task', 'https://brainmate.vercel.app');
    }
}