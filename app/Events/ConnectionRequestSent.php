<?php

namespace App\Events;

use App\Models\User;
use App\Models\UserRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConnectionRequestSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $sender;
    public $receiver;
    public $request;

    public function __construct(User $sender, User $receiver, UserRequest $request)
    {
        $this->sender = $sender;
        $this->receiver = $receiver;
        $this->request = $request;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('user.' . $this->receiver->id);
    }

    public function broadcastAs()
    {
        return 'connection.request.sent';
    }

    public function broadcastWith()
    {
        return [
            'sender' => [
                'id' => $this->sender->id,
                'name' => $this->sender->name,
                'profile_image' => $this->sender->profile_image_url,
            ],
            'request_id' => $this->request->id,
            'message' => $this->sender->name . ' sent you a connection request'
        ];
    }
}
