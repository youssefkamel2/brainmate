<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Chat;

class LastMessageUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $chat, $color;

    /**
     * Create a new event instance.
     */
    public function __construct(Chat $chat, $color)
    {
        // Eager load the sender relationship
        $this->chat = $chat->load('sender');
        $this->color = $color;

    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('last-message-updates'),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'team_id' => $this->chat->team_id,
            'last_message' => [
                'message' => $this->chat->message,
                'timestamp' => $this->chat->created_at,
                'sender' => [
                    'id' => $this->chat->sender->id,
                    'name' => $this->chat->sender->name,
                    'color' => $this->color,
                ],
            ],
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs()
    {
        return 'last-message-updated';
    }
}