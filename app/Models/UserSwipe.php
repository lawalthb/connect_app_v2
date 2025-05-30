<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class UserSwipe extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'swipe_date',
        'left_swipes',
        'right_swipes',
        'super_likes',
        'total_swipes',
        'archived_at'
    ];

    protected $casts = [
        'swipe_date' => 'date',
        'archived_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get or create today's swipe record for a user
     */
    public static function getTodayRecord($userId)
    {
        return static::firstOrCreate([
            'user_id' => $userId,
            'swipe_date' => Carbon::today()
        ], [
            'left_swipes' => 0,
            'right_swipes' => 0,
            'super_likes' => 0,
            'total_swipes' => 0
        ]);
    }

    /**
     * Increment swipe count
     */
    public function incrementSwipe($type = 'right')
    {
        switch ($type) {
            case 'left':
                $this->increment('left_swipes');
                break;
            case 'super':
                $this->increment('super_likes');
                $this->increment('right_swipes');
                break;
            default:
                $this->increment('right_swipes');
                break;
        }
        $this->increment('total_swipes');
        $this->save();
    }
}
