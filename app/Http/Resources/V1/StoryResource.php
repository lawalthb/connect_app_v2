<?php
namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoryResource extends JsonResource
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
            'type' => $this->type,
            'story' => $this->story,
            'url' => $this->url ? url($this->url . '/' . $this->story) : null,
            'message' => $this->message,
            'user' => new UserResource($this->whenLoaded('user')),
            'tagged_users' => UserResource::collection($this->whenLoaded('taggedUsers')),
            'views_count' => $this->whenCounted('views'),
            'view_flag' => $this->when(auth()->check(), function () {
                return $this->views()->where('user_id', auth()->id())->exists();
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
