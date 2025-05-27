<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CallParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'call_id',
        'user_id',
        'status',
        'agora_token',
        'agora_uid',
        'invited_at',
        'joined_at',
        'left_at',
        'duration',
    ];

    protected $casts = [
        'invited_at' => 'datetime',
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
    ];

    /**
     * Get the call this participant belongs to
     */
    public function call()
    {
        return $this->belongsTo(Call::class);
    }

    /**
     * Get the user for this participant
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Calculate and update participation duration
     */
    public function updateDuration(): void
    {
        if ($this->joined_at && $this->left_at) {
            $this->duration = $this->left_at->diffInSeconds($this->joined_at);
            $this->save();
        }
    }

    /**
     * Generate unique Agora UID
     */
    public static function generateAgoraUid(): int
    {
        return random_int(100000, 999999);
    }
}
