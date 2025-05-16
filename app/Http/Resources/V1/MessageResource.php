<?php
namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'message' => $this->message,
            'message_url' => $this->message_url ? url($this->message_url . '/' . $this->message) : null,
            'type' => $this->type,
            'sender_id' => $this->sender_id,
            'receiver_id' => $this->receiver_id,
            'receive_flag' => $this->receive_flag,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
