<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoryReply extends Model
{
    use HasFactory;

    protected $fillable = [
        'story_id',
        'user_id',
        'type',
        'content',
        'file_url',
    ];

    public function story()
    {
        return $this->belongsTo(Story::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
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
}
