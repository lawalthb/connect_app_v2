<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserSocialCircle extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'user_social_circle';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'social_id',
        'deleted_flag',
        'created_by',
        'updated_by',
        'deleted_by'
    ];
    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->deleted_flag = 'N';
            if (auth()->check()) {
                $model->created_by = auth()->id();
            }
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });
    }
    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::creating(function ($model) {
            if (auth()->check()) {
                $model->created_by = auth()->id();
            }
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });

        static::deleting(function ($model) {
            if (auth()->check()) {
                $model->deleted_by = auth()->id();
                $model->deleted_flag = 'Y';
                $model->save();
            }
        });
    }

    /**
     * Get the user that belongs to this pivot.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the social circle that belongs to this pivot.
     */
    public function socialCircle()
    {
        return $this->belongsTo(SocialCircle::class, 'social_id');
    }

    /**
     * Scope a query to only include active records.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('deleted_flag', 'N');
    }
}
