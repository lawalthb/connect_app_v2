<?php
// app/Http/Resources/V1/PostResource.php (continued)
namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
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
            'file' => $this->file,
            'file_url' => $this->file_url ? url($this->file_url . '/' . $this->file) : null,
            'type' => $this->type,
            'social_id' => $this->social_id,
            'user' => new UserResource($this->whenLoaded('user')),
            'tagged_users' => UserResource::collection($this->whenLoaded('taggedUsers')),
            'comments_count' => $this->whenCounted('comments'),
            'likes_count' => $this->whenCounted('likes'),
            'comments' => CommentResource::collection($this->whenLoaded('comments')),
            'likes' => LikeResource::collection($this->whenLoaded('likes')),
            'user_has_liked' => $this->whenLoaded('likes', function () use ($request) {
                return $this->likes->contains('user_id', $request->user()->id);
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
