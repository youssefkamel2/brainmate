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

        public $chat;

        /**
         * Create a new event instance.
         */
        public function __construct(Chat $chat)
        {
            $this->chat = $chat->load('sender');
        }

        /**
         * Get the channels the event should broadcast on.
         */
        public function broadcastOn(): array
        {
            return [
                new Channel('team.' . $this->chat->team_id),
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