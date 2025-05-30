<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConnectionAccepted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $sender;
    public $receiver;

    public function __construct(User $sender, User $receiver)
    {
        $this->sender = $sender;
        $this->receiver = $receiver;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('user.' . $this->sender->id);
    }

    public function broadcastAs()
    {
        return 'connection.accepted';
    }

    public function broadcastWith()
    {
        return [
            'user' => [
                'id' => $this->receiver->id,
                'name' => $this->receiver->name,
                'profile_image' => $this->receiver->profile_image_url,
            ],
            'message' => $this->receiver->name . ' accepted your connection request'
        ];
    }
}
