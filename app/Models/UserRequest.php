<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class UserRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'sender_id',
        'receiver_id',
        'social_id',
        'status',
        'sender_status',
        'receiver_status',
        'request_type',
        'message',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    // Relationships
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function socialCircle()
    {
        return $this->belongsTo(SocialCircle::class, 'social_id');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeAccepted($query)
    {
        return $query->where('status', 'accepted');
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('receiver_id', $userId);
    }

    public function scopeFromUser($query, $userId)
    {
        return $query->where('sender_id', $userId);
    }

    // Methods
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isMutualMatch(): bool
    {
        return $this->status === 'accepted' &&
               $this->sender_status === 'accepted' &&
               $this->receiver_status === 'accepted';
    }
}
