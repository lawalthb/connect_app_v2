<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoryViewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'viewer' => [
                'id' => $this->viewer->id,
                'name' => $this->viewer->name,
                'username' => $this->viewer->username,
                'profile_image' => $this->viewer->profile_image_url,
            ],
            'viewed_at' => $this->viewed_at->toISOString(),
        ];
    }
}
