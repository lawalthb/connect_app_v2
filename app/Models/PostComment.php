<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PostComment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'post_id',
        'user_id',
        'parent_id',
        'content',
        'likes_count',
        'replies_count',
        'is_edited',
        'edited_at'
    ];

    protected $casts = [
        'likes_count' => 'integer',
        'replies_count' => 'integer',
        'is_edited' => 'boolean',
        'edited_at' => 'datetime',
    ];

    protected $appends = [
        'time_since_created',
        'can_edit'
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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(PostComment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(PostComment::class, 'parent_id')->latest();
    }

    public function likes(): HasMany
    {
        return $this->hasMany(PostCommentLike::class, 'comment_id');
    }

    // Accessors
    public function getTimeSinceCreatedAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    public function getCanEditAttribute(): bool
    {
        return $this->created_at->isToday() &&
               auth()->check() &&
               auth()->id() === $this->user_id;
    }

    // Methods
    public function markAsEdited(): void
    {
        $this->update([
            'is_edited' => true,
            'edited_at' => now()
        ]);
    }

    public function incrementReplies(): void
    {
        $this->increment('replies_count');
    }

    public function decrementReplies(): void
    {
        $this->decrement('replies_count');
    }

    // Events
    protected static function booted()
    {
        static::created(function ($comment) {
            $comment->post->incrementComments();

            if ($comment->parent_id) {
                $comment->parent->incrementReplies();
            }
        });

        static::deleted(function ($comment) {
            $comment->post->decrementComments();

            if ($comment->parent_id) {
                $comment->parent->decrementReplies();
            }
        });
    }
}
