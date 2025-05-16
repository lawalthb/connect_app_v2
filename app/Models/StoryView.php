<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoryView extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'story_id',
    ];

    /**
     * Get the user that owns the view.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the story that owns the view.
     */
    public function story()
    {
        return $this->belongsTo(Story::class);
    }
}
