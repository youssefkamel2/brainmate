<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Task;

class TaskStatusUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $task;

    public function __construct(Task $task)
    {
        $this->task = $task;
    }

    public function via($notifiable)
    {
        return ['mail'];
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