<?php

namespace App\Events;

use App\Models\Call;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallMissed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $call;

    public function __construct(Call $call)
    {
        $this->call = $call;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('conversation.' . $this->call->conversation_id);
    }

    public function broadcastAs()
    {
        return 'call.missed';
    }

    public function broadcastWith()
    {
        return [
            'call_id' => $this->call->id,
            'call_type' => $this->call->call_type,
            'initiator' => [
                'id' => $this->call->initiator->id,
                'name' => $this->call->initiator->name,
            ],
            'missed_at' => $this->call->ended_at?->toISOString(),
        ];
    }
}
