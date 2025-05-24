<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class SocialCircle extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'logo',
        'logo_url',
        'description',
        'order_by',
        'color',
        'is_default',
        'is_active',
        'is_private',
        'created_by',
        'updated_by'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'is_private' => 'boolean',
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::addGlobalScope('active', function (Builder $builder) {
            $builder->where('social_circles.is_active', true)
                   ->where('social_circles.deleted_flag', 'N');
        });
    }

    /**
     * Get the full logo URL.
     *
     * @return string|null
     */
    public function getLogoFullUrlAttribute()
    {
        if (!$this->logo) {
            return null;
        }

        // Using asset() for local storage or full URL for external storage
        $base = $this->logo_url ? url($this->logo_url) : '';
        return $base ? "{$base}/{$this->logo}" : asset("storage/{$this->logo}");
    }

    /**
     * Get the users that belong to the social circle.
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_social_circles', 'social_id', 'user_id')
            ->withTimestamps()
            ->withPivot(['deleted_at', 'deleted_flag'])
            ->wherePivot('deleted_flag', 'N')
            ->where('users.deleted_flag', 'N');
    }

    /**
     * Get the user who created this social circle.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get active social circles.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('social_circles.is_active', true)
                    ->where('social_circles.deleted_flag', 'N');
    }

    /**
     * Get default social circles.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDefault($query)
    {
        return $query->where('social_circles.is_default', true);
    }

    /**
     * Order by the order_by field.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('social_circles.order_by');
    }

    /**
     * Get the posts that belong to the social circle.
     */
    public function posts()
    {
        return $this->hasMany(Post::class, 'social_id');
    }
}
