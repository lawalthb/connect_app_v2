<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoryReplyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'username' => $this->user->username,
                'profile_image' => $this->user->profile_image_url,
            ],
            'type' => $this->type,
            'content' => $this->content,
            'file_url' => $this->full_file_url,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
