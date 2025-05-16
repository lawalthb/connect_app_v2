<?php
// app/Models/SocialCircle.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SocialCircle extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'icon',
    ];

    /**
     * Get the users that belong to the social circle.
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_social_circles')
            ->withTimestamps();
    }

    /**
     * Get the posts that belong to the social circle.
     */
    public function posts()
    {
        return $this->hasMany(Post::class, 'social_id');
    }
}
