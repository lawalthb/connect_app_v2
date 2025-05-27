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

class CallEnded implements ShouldBroadcast
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
        return 'call.ended';
    }

    public function broadcastWith()
    {
        return [
            'call_id' => $this->call->id,
            'duration' => $this->call->duration,
            'formatted_duration' => $this->call->formatted_duration,
            'ended_by' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
            'ended_at' => $this->call->ended_at?->toISOString(),
        ];
    }
}
