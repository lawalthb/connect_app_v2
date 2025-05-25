<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Helpers\TimezoneHelper;

class ConversationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentUser = $request->user();

        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->getConversationName($currentUser),
            'description' => $this->description,
            'image' => $this->getConversationImage($currentUser),
            'participants_count' => $this->activeParticipants->count(),
            'participants' => $this->when($this->relationLoaded('users'), function () {
                return $this->users->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'username' => $user->username,
                        'profile_url' => $user->profile_url,
                        'role' => $user->pivot->role,
                        'is_online' => $user->is_online,
                    ];
                });
            }),
            'latest_message' => $this->when($this->relationLoaded('latestMessage') && $this->latestMessage, function () {
                return new MessageResource($this->latestMessage);
            }),
            'unread_count' => $this->getUnreadCountForUser($currentUser->id),
            'last_message_at' => $this->last_message_at ? TimezoneHelper::convertToUserTimezone($this->last_message_at) : null,
            'created_at' => TimezoneHelper::convertToUserTimezone($this->created_at),
        ];
    }

    /**
     * Get conversation name for display
     */
    private function getConversationName($currentUser)
    {
        if ($this->type === 'group') {
            return $this->name;
        }

        // For private conversations, show the other participant's name
        $otherParticipant = $this->users->where('id', '!=', $currentUser->id)->first();
        return $otherParticipant ? $otherParticipant->name : 'Unknown User';
    }

    /**
     * Get conversation image for display
     */
    private function getConversationImage($currentUser)
    {
        if ($this->type === 'group') {
            return $this->image;
        }

        // For private conversations, show the other participant's profile image
        $otherParticipant = $this->users->where('id', '!=', $currentUser->id)->first();
        return $otherParticipant ? $otherParticipant->profile_url : null;
    }
}
