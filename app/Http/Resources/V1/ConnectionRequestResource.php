<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class ConnectionRequestResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'sender' => new UserProfileResource($this->whenLoaded('sender')),
            'receiver' => new UserProfileResource($this->whenLoaded('receiver')),
            'status' => $this->status,
            'request_type' => $this->request_type,
            'message' => $this->message,
            'social_circle' => $this->whenLoaded('socialCircle', [
                'id' => $this->socialCircle->id,
                'name' => $this->socialCircle->name,
            ]),
            'created_at' => $this->created_at->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
        ];
    }
}
