<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Call extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'initiated_by',
        'call_type',
        'status',
        'agora_channel_name',
        'agora_tokens',
        'started_at',
        'connected_at',
        'ended_at',
        'duration',
        'end_reason',
    ];

    protected $casts = [
        'agora_tokens' => 'array',
        'started_at' => 'datetime',
        'connected_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    /**
     * Get the conversation this call belongs to
     */
    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the user who initiated the call
     */
    public function initiator()
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    /**
     * Get all participants in this call
     */
    public function participants()
    {
        return $this->hasMany(CallParticipant::class);
    }

    /**
     * Get participants with their user data
     */
    public function participantsWithUsers()
    {
        return $this->participants()->with('user');
    }

    /**
     * Check if call is active
     */
    public function isActive(): bool
    {
        return in_array($this->status, ['initiated', 'ringing', 'connected']);
    }

    /**
     * Check if call is ended
     */
    public function isEnded(): bool
    {
        return in_array($this->status, ['ended', 'missed']);
    }

    /**
     * Get formatted duration
     */
    public function getFormattedDurationAttribute(): string
    {
        if ($this->duration < 60) {
            return $this->duration . 's';
        } elseif ($this->duration < 3600) {
            return floor($this->duration / 60) . 'm ' . ($this->duration % 60) . 's';
        } else {
            $hours = floor($this->duration / 3600);
            $minutes = floor(($this->duration % 3600) / 60);
            $seconds = $this->duration % 60;
            return $hours . 'h ' . $minutes . 'm ' . $seconds . 's';
        }
    }

    /**
     * Calculate and update call duration
     */
    public function updateDuration(): void
    {
        if ($this->connected_at && $this->ended_at) {
            $this->duration = $this->ended_at->diffInSeconds($this->connected_at);
            $this->save();
        }
    }

    /**
     * Generate unique channel name
     */
    public static function generateChannelName(): string
    {
        return 'call_' . time() . '_' . uniqid();
    }
}
