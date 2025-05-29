<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoryResource extends JsonResource
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
            'caption' => $this->caption,
            'background_color' => $this->background_color,
            'font_settings' => $this->font_settings,
            'privacy' => $this->privacy,
            'allow_replies' => $this->allow_replies,
            'views_count' => $this->views_count,
            'is_expired' => $this->is_expired,
            'time_left' => $this->time_left,
            'created_at' => $this->created_at->toISOString(),
            'expires_at' => $this->expires_at->toISOString(),

            // Include additional data based on context
            'has_viewed' => $this->when(
                $request->user() && $request->user()->id !== $this->user_id,
                function () use ($request) {
                    return $this->views()
                        ->where('viewer_id', $request->user()->id)
                        ->exists();
                }
            ),

            'viewers' => $this->when(
                $request->user() && $request->user()->id === $this->user_id,
                StoryViewResource::collection($this->views()->with('viewer')->latest('viewed_at')->get())
            ),
        ];
    }
}
