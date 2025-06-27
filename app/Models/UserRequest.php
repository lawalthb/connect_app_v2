<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'user_requests';

    protected $fillable = [
        'sender_id',
        'receiver_id',
        'request_type',
        'message',
        'status',
        'sender_status',
        'receiver_status',
        'social_id',
        'responded_at',
        'disconnected_at',
        'created_by',
        'updated_by',
        'deleted_flag'
    ];

    protected $dates = [
        'responded_at',
        'disconnected_at',
        'created_at',
        'updated_at',
        'deleted_at'
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
