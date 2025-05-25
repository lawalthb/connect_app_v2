<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conversation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'type',
        'name',
        'description',
        'image',
        'created_by',
        'last_message_at',
        'is_active'
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'is_active' => 'boolean'
    ];

    /**
     * Get the user who created the conversation
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all participants in the conversation
     */
    public function participants()
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    /**
     * Get active participants in the conversation
     */
    public function activeParticipants()
    {
        return $this->hasMany(ConversationParticipant::class)->where('is_active', true);
    }

    /**
     * Get all users in the conversation
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
                    ->withPivot(['role', 'joined_at', 'left_at', 'last_read_at', 'is_active'])
                    ->wherePivot('is_active', true);
    }

    /**
     * Get all messages in the conversation
     */
    public function messages()
    {
        return $this->hasMany(Message::class)->where('is_deleted', false);
    }

    /**
     * Get the latest message in the conversation
     */
    public function latestMessage()
    {
        return $this->hasOne(Message::class)->latest()->where('is_deleted', false);
    }

    /**
     * Get unread messages count for a specific user
     */
    public function getUnreadCountForUser($userId)
    {
        $participant = $this->participants()->where('user_id', $userId)->first();

        if (!$participant || !$participant->last_read_at) {
            return $this->messages()->count();
        }

        return $this->messages()
                    ->where('created_at', '>', $participant->last_read_at)
                    ->where('user_id', '!=', $userId)
                    ->count();
    }

    /**
     * Check if user is participant
     */
    public function hasParticipant($userId)
    {
        return $this->participants()->where('user_id', $userId)->where('is_active', true)->exists();
    }
}
