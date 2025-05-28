<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id',
        'reported_by',
        'reason',
        'description',
        'status',
        'reviewed_by',
        'reviewed_at',
        'admin_notes'
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    const REASONS = [
        'spam' => 'Spam',
        'inappropriate_content' => 'Inappropriate Content',
        'harassment' => 'Harassment',
        'hate_speech' => 'Hate Speech',
        'violence' => 'Violence',
        'false_information' => 'False Information',
        'copyright_violation' => 'Copyright Violation',
        'other' => 'Other'
    ];

    const STATUSES = [
        'pending' => 'Pending',
        'under_review' => 'Under Review',
        'dismissed' => 'Dismissed',
        'action_taken' => 'Action Taken',
        'resolved' => 'Resolved'
    ];

    // Relationships
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // Accessors
    public function getReasonTextAttribute(): string
    {
        return self::REASONS[$this->reason] ?? 'Unknown';
    }

    public function getStatusTextAttribute(): string
    {
        return self::STATUSES[$this->status] ?? 'Unknown';
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeUnderReview($query)
    {
        return $query->where('status', 'under_review');
    }
}
