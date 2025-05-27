<?php

namespace App\Events;

use App\Models\Call;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallInitiated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $call;
    public $conversation;
    public $initiator;

    public function __construct(Call $call, Conversation $conversation, User $initiator)
    {
        $this->call = $call;
        $this->conversation = $conversation;
        $this->initiator = $initiator;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('conversation.' . $this->conversation->id);
    }

    public function broadcastAs()
    {
        return 'call.initiated';
    }

    public function broadcastWith()
    {
        return [
            'call_id' => $this->call->id,
            'call_type' => $this->call->call_type,
            'agora_channel_name' => $this->call->agora_channel_name,
            'initiator' => [
                'id' => $this->initiator->id,
                'name' => $this->initiator->name,
                'username' => $this->initiator->username,
                'profile_url' => $this->initiator->profile_url,
            ],
            'started_at' => $this->call->started_at->toISOString(),
        ];
    }
}
