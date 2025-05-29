<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Story extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'type',
        'content',
        'file_url',
        'caption',
        'background_color',
        'font_settings',
        'privacy',
        'custom_viewers',
        'allow_replies',
        'views_count',
        'expires_at',
    ];

    protected $casts = [
        'font_settings' => 'array',
        'custom_viewers' => 'array',
        'allow_replies' => 'boolean',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = ['is_expired', 'time_left'];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function views()
    {
        return $this->hasMany(StoryView::class);
    }

    public function replies()
    {
        return $this->hasMany(StoryReply::class);
    }

    // Accessors
    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at->isPast();
    }

    public function getTimeLeftAttribute(): int
    {
        if ($this->is_expired) {
            return 0;
        }
        return $this->expires_at->diffInSeconds(now());
    }

    public function getFullFileUrlAttribute(): ?string
    {
        if (!$this->file_url) {
            return null;
        }

        if (str_starts_with($this->file_url, 'http')) {
            return $this->file_url;
        }

        return config('app.url') . '/storage/' . $this->file_url;
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('expires_at', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    public function scopeVisibleTo($query, $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('privacy', 'all_connections')
              ->orWhere(function ($subQ) use ($userId) {
                  $subQ->where('privacy', 'custom')
                       ->whereJsonContains('custom_viewers', $userId);
              });
        });
    }

    // Methods
    public function markAsViewed($viewerId): void
    {
        if ($viewerId === $this->user_id) {
            return; // Don't count self-views
        }

        $view = StoryView::firstOrCreate([
            'story_id' => $this->id,
            'viewer_id' => $viewerId,
        ], [
            'viewed_at' => now(),
        ]);

        if ($view->wasRecentlyCreated) {
            $this->increment('views_count');
        }
    }

    public function canBeViewedBy($userId): bool
    {
        if ($this->user_id === $userId) {
            return true; // Owner can always view
        }

        if ($this->is_expired) {
            return false;
        }

        switch ($this->privacy) {
            case 'all_connections':
                // Check if users are connected
                return $this->user->isConnectedWith($userId);

            case 'custom':
                return in_array($userId, $this->custom_viewers ?? []);

            default:
                return false;
        }
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($story) {
            // Set expiration to 24 hours from now
            $story->expires_at = now()->addHours(24);
        });
    }
}
