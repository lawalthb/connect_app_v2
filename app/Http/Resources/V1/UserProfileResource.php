<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class UserProfileResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'bio' => $this->bio,
            'profile_image' => $this->profile_image_url,
            'profile_images' => ProfileImageResource::collection($this->whenLoaded('profileImages')),
            'country' => $this->whenLoaded('country', [
                'id' => $this->country->id,
                'name' => $this->country->name,
            ]),
            'social_circles' => SocialCircleResource::collection($this->whenLoaded('socialCircles')),
            'stats' => [
                'total_connections' => $this->total_connections ?? 0,
                'total_likes' => $this->total_likes ?? 0,
                'total_posts' => $this->total_posts ?? 0,
            ],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
