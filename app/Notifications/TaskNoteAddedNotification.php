<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Task;
use App\Models\TaskNote;

class TaskNoteAddedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $task;
    protected $taskNote;

    public function __construct(Task $task, TaskNote $taskNote)
    {
        $this->task = $task;
        $this->taskNote = $taskNote;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('New Note Added to Task: ' . $this->task->name)
            ->line('A new note has been added to the task.')
            ->line('**Task Details:**')
            ->line('Name: ' . $this->task->name)
            ->line('Description: ' . $this->task->description)
            ->line('Priority: ' . ucfirst($this->task->priority))
            ->line('Deadline: ' . $this->task->deadline)
            ->line('**Note Details:**')
            ->line('Added By: ' . $this->taskNote->user->name)
            ->line('Note: ' . $this->taskNote->description)
            ->action('View Task', 'https://brainmate.vercel.app');
    }
}