<?php

namespace App\Events;

use App\Models\Call;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallAnswered implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $call;
    public $user;

    public function __construct(Call $call, User $user)
    {
        $this->call = $call;
        $this->user = $user;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('conversation.' . $this->call->conversation_id);
    }

    public function broadcastAs()
    {
        return 'call.answered';
    }

    public function broadcastWith()
    {
        return [
            'call_id' => $this->call->id,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'username' => $this->user->username,
            ],
            'connected_at' => $this->call->connected_at?->toISOString(),
        ];
    }
}
