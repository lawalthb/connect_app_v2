<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostLike extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id',
        'user_id',
        'reaction_type'
    ];

    const REACTION_TYPES = [
        'like' => '👍',
        'love' => '❤️',
        'laugh' => '😂',
        'angry' => '😡',
        'sad' => '😢',
        'wow' => '😮'
    ];

    protected $appends = ['reaction_emoji'];

    // Relationships
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Accessors
    public function getReactionEmojiAttribute(): string
    {
        return self::REACTION_TYPES[$this->reaction_type] ?? '👍';
    }

    // Events
    protected static function booted()
    {
        static::created(function ($like) {
            $like->post->incrementLikes();
        });

        static::deleted(function ($like) {
            $like->post->decrementLikes();
        });

        static::updated(function ($like) {
            // If reaction type changed, no counter change needed
            // since it's still one reaction per user
        });
    }
}
