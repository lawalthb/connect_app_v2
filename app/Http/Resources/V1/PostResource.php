<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'username' => $this->user->username,
                'profile' => $this->user->profile,
                'profile_url' => $this->user->profile_url,
                'full_profile_url' => $this->user->profile_url ?
                    (str_starts_with($this->user->profile_url, 'http') ?
                        $this->user->profile_url . $this->user->profile :
                        config('app.url') . '/' . $this->user->profile_url . $this->user->profile
                    ) : null,
            ],
            'social_circle' => [
                'id' => $this->socialCircle->id,
                'name' => $this->socialCircle->name,
                'color' => $this->socialCircle->color,
                'icon' => $this->socialCircle->icon ?? null,
            ],
            'content' => $this->content,
            'type' => $this->type,
            'location' => $this->location,
            'media' => PostMediaResource::collection($this->whenLoaded('media')),
            'tagged_users' => UserMiniResource::collection($this->whenLoaded('taggedUsers')),
            'is_edited' => $this->is_edited,
            'edited_at' => $this->edited_at?->toISOString(),
            'published_at' => $this->published_at?->toISOString(),
            'scheduled_at' => $this->scheduled_at?->toISOString(),
            'is_scheduled' => $this->is_scheduled,
            'can_edit' => $this->can_edit,
            'time_since_created' => $this->time_since_created,

            // Engagement metrics
            'likes_count' => $this->likes_count,
            'comments_count' => $this->comments_count,
            'shares_count' => $this->shares_count,
            'views_count' => $this->views_count,

            // User interaction
            'user_reaction' => $this->when(
                isset($this->user_reaction),
                $this->user_reaction ? [
                    'type' => $this->user_reaction->reaction_type,
                    'emoji' => $this->user_reaction->reaction_emoji,
                ] : null
            ),
            'has_user_liked' => $this->when(isset($this->has_user_liked), $this->has_user_liked),
            'reaction_counts' => $this->when(isset($this->reaction_counts), $this->reaction_counts),

            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
