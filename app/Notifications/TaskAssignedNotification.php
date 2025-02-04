<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Task;

class TaskAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $task; // The task assigned to the user

    public function __construct(Task $task)
    {
        $this->task = $task;
    }

    /**
     * Get the notification delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('New Task Assigned: ' . $this->task->name)
            ->line('You have been assigned a new task.')
            ->line('**Task Details:**')
            ->line('Name: ' . $this->task->name)
            ->line('Description: ' . $this->task->description)
            ->line('Priority: ' . ucfirst($this->task->priority))
            ->line('Deadline: ' . $this->task->deadline)
            ->action('View Task', 'https://brainmate.vercel.app') // Replace with your task URL
            ->line('Thank you for using our application!');
    }
}