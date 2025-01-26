<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $team;
    protected $project;
    protected $role;
    protected $token;

    public function __construct($team, $project, $role, $token)
    {
        $this->team = $team;
        $this->project = $project;
        $this->role = $role;
        $this->token = $token;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('You have been invited to a team!')
            ->line("You have been invited to join the team '{$this->team->name}' in the project '{$this->project->name}' as a {$this->role}.")
            ->action('Accept Invitation', url("https://brainmate.vercel.app/accept-invitation?token={$this->token}"))
            ->line('If you did not expect this invitation, no further action is required.');
    }
}