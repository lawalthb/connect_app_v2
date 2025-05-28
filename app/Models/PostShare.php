<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostShare extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id',
        'user_id',
        'shared_to',
        'platform'
    ];

    const SHARE_TYPES = [
        'external_social_media' => 'External Social Media',
        'direct_message' => 'Direct Message',
        'email' => 'Email',
        'link_copy' => 'Link Copy',
        'other' => 'Other'
    ];

    // Relationships
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Events
    protected static function booted()
    {
        static::created(function ($share) {
            $share->post->incrementShares();
        });
    }
}
