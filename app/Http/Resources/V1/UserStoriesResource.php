<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserStoriesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $activeStories = $this->activeStories;
        $hasUnviewedStories = false;

        if ($request->user() && $request->user()->id !== $this->id) {
            $hasUnviewedStories = $activeStories->filter(function ($story) use ($request) {
                return !$story->views()->where('viewer_id', $request->user()->id)->exists();
            })->isNotEmpty();
        }

        return [
            'user' => [
                'id' => $this->id,
                'name' => $this->name,
                'username' => $this->username,
                'profile_image' => $this->profile_image_url,
            ],
            'stories_count' => $activeStories->count(),
            'has_unviewed_stories' => $hasUnviewedStories,
            'latest_story_time' => $activeStories->first()?->created_at?->toISOString(),
            'stories' => StoryResource::collection($activeStories),
        ];
    }
}
