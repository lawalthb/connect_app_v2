<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Carbon\Carbon;

class Post extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'social_circle_id',
        'content',
        'type',
        'location',
        'is_edited',
        'edited_at',
        'is_published',
        'published_at',
        'scheduled_at',
        'likes_count',
        'comments_count',
        'shares_count',
        'views_count',
        'metadata'
    ];

    protected $casts = [
        'location' => 'array',
        'metadata' => 'array',
        'is_edited' => 'boolean',
        'is_published' => 'boolean',
        'edited_at' => 'datetime',
        'published_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'likes_count' => 'integer',
        'comments_count' => 'integer',
        'shares_count' => 'integer',
        'views_count' => 'integer',
    ];

    protected $appends = [
        'can_edit',
        'time_since_created',
        'is_scheduled'
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function socialCircle(): BelongsTo
    {
        return $this->belongsTo(SocialCircle::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(PostMedia::class)->orderBy('order');
    }

    public function likes(): HasMany
    {
        return $this->hasMany(PostLike::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PostComment::class)->whereNull('parent_id')->latest();
    }

    public function allComments(): HasMany
    {
        return $this->hasMany(PostComment::class);
    }

    public function taggedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'post_tagged_users')
                    ->withPivot(['tagged_by', 'media_id', 'position_x', 'position_y'])
                    ->withTimestamps();
    }

    public function reports(): HasMany
    {
        return $this->hasMany(PostReport::class);
    }

    public function views(): HasMany
    {
        return $this->hasMany(PostView::class);
    }

    public function shares(): HasMany
    {
        return $this->hasMany(PostShare::class);
    }

    // Scopes
    public function scopePublished($query)
    {
        return $query->where('is_published', true)
                    ->where(function ($q) {
                        $q->whereNull('scheduled_at')
                          ->orWhere('scheduled_at', '<=', now());
                    });
    }

    public function scopeScheduled($query)
    {
        return $query->where('scheduled_at', '>', now());
    }

    public function scopeByCircle($query, $circleId)
    {
        return $query->where('social_circle_id', $circleId);
    }

    public function scopeWithUserReaction($query, $userId)
    {
        return $query->with(['likes' => function ($q) use ($userId) {
            $q->where('user_id', $userId);
        }]);
    }

    // Accessors
    public function getCanEditAttribute(): bool
    {
        // Can edit only on the same day and only by the author
        return $this->created_at->isToday() &&
               auth()->check() &&
               auth()->id() === $this->user_id;
    }

    public function getTimeSinceCreatedAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    public function getIsScheduledAttribute(): bool
    {
        return $this->scheduled_at && $this->scheduled_at->isFuture();
    }

    // Methods
    public function incrementLikes(): void
    {
        $this->increment('likes_count');
    }

    public function decrementLikes(): void
    {
        $this->decrement('likes_count');
    }

    public function incrementComments(): void
    {
        $this->increment('comments_count');
    }

    public function decrementComments(): void
    {
        $this->decrement('comments_count');
    }

    public function incrementViews(): void
    {
        $this->increment('views_count');
    }

    public function incrementShares(): void
    {
        $this->increment('shares_count');
    }

    public function markAsEdited(): void
    {
        $this->update([
            'is_edited' => true,
            'edited_at' => now()
        ]);
    }

    public function getUserReaction($userId = null): ?PostLike
    {
        $userId = $userId ?? auth()->id();
        return $this->likes()->where('user_id', $userId)->first();
    }

    public function hasUserLiked($userId = null): bool
    {
        return $this->getUserReaction($userId) !== null;
    }

    public function getReactionCounts(): array
    {
        return $this->likes()
                   ->selectRaw('reaction_type, count(*) as count')
                   ->groupBy('reaction_type')
                   ->pluck('count', 'reaction_type')
                   ->toArray();
    }
}
