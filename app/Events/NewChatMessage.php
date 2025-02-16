<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Chat;

class NewChatMessage implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $chat, $color;

    public function __construct(Chat $chat, $color)
    {
        // Eager load the sender relationship
        $this->chat = $chat->load('sender');
        $this->color = $color;
    }

    public function broadcastOn()
    {
        // Broadcast to a channel named after the team ID
        return new Channel('team.' . $this->chat->team_id);
    }

    public function broadcastAs()
    {
        // Use a custom event name
        return 'new-chat-message';
    }

    public function broadcastWith()
    {
        // Include the chat message data and sender object in the broadcast
        return [
            'id' => $this->chat->id,
            'sender_id' => $this->chat->sender_id,
            'receiver_id' => $this->chat->receiver_id,
            'team_id' => $this->chat->team_id,
            'message' => $this->chat->message,
            'type' => $this->chat->type,
            'media' => $this->chat->media,
            'created_at' => $this->chat->created_at,
            'updated_at' => $this->chat->updated_at,
            'sender' => [
                'id' => $this->chat->sender->id,
                'name' => $this->chat->sender->name,
                'color' => $this->color,
            ],
        ];
    }
}