<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'message',
        'file',
        'file_url',
        'type',
        'social_id',
    ];

    /**
     * Get the user that owns the post.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the tagged users for the post.
     */
    public function taggedUsers()
    {
        return $this->belongsToMany(User::class, 'post_tagged', 'post_id', 'user_id')
            ->withTimestamps();
    }

    /**
     * Get the comments for the post.
     */
    public function comments()
    {
        return $this->hasMany(PostComment::class);
    }

    /**
     * Get the likes for the post.
     */
    public function likes()
    {
        return $this->hasMany(PostLike::class);
    }

    /**
     * Check if the post is public.
     */
    public function isPublic()
    {
        // Implement your logic here
        return true;
    }
}
