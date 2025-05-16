<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Story extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'type',
        'story',
        'url',
        'message',
    ];

    /**
     * Get the user that owns the story.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the tagged users for the story.
     */
    public function taggedUsers()
    {
        return $this->belongsToMany(User::class, 'story_tagged', 'story_id', 'user_id')
            ->withTimestamps();
    }

    /**
     * Get the views for the story.
     */
    public function views()
    {
        return $this->hasMany(StoryView::class);
    }
}
