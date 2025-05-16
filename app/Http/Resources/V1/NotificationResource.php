<?php
namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
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
            'notification' => $this->notification,
            'notification_title' => $this->notification_title,
            'notification_type' => $this->notification_type,
            'object_id' => $this->object_id,
            'sender_id' => $this->sender_id,
            'sender' => $this->when($this->sender_id, function () {
                $user = \App\Models\User::find($this->sender_id);
                if ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'profile' => $user->profile,
                        'profile_url' => $user->profile_url,
                    ];
                }
                return null;
            }),
            'receive_flag' => $this->receive_flag,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
