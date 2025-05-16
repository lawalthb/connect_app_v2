<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'username',
        'bio',
        'profile_image',
        'country_id',
        'phone',
        'social_links',
        'verification_token',
        'last_login_at',
        'last_login_ip',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'verification_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'social_links' => 'array',
        'last_login_at' => 'datetime',
    ];

    /**
     * Get the country that the user belongs to.
     */
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    /**
 * Get the social circles that belong to the user.
 */
public function socialCircles()
{
    return $this->belongsToMany(SocialCircle::class, 'user_social_circles', 'user_id', 'social_id')
        ->where('user_social_circles.deleted_flag', 'N')
        ->withTimestamps();
}

/**
 * Get the posts that belong to the user.
 */
public function posts()
{
    return $this->hasMany(Post::class);
}

/**
 * Get the comments that belong to the user.
 */
public function comments()
{
    return $this->hasMany(PostComment::class);
}

/**
 * Get the likes that belong to the user.
 */
public function likes()
{
    return $this->hasMany(PostLike::class);
}

/**
 * Get the user's connections (users they are connected with).
 */
public function connections()
{
    return $this->belongsToMany(User::class, 'user_requests', 'sender_id', 'receiver_id')
        ->where('status', 'Accepted')
        ->withTimestamps()
        ->withPivot('status', 'sender_status', 'receiver_status', 'social_id');
}

/**
 * Get the user's incoming connection requests.
 */
public function incomingRequests()
{
    return $this->belongsToMany(User::class, 'user_requests', 'receiver_id', 'sender_id')
        ->where('status', 'Pending')
        ->withTimestamps()
        ->withPivot('status', 'sender_status', 'receiver_status', 'social_id');
}

/**
 * Get the user's outgoing connection requests.
 */
public function outgoingRequests()
{
    return $this->belongsToMany(User::class, 'user_requests', 'sender_id', 'receiver_id')
        ->where('status', 'Pending')
        ->withTimestamps()
        ->withPivot('status', 'sender_status', 'receiver_status', 'social_id');
}


}
