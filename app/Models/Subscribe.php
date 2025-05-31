<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscribe extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'currency',
        'duration_days',
        'features',
        'stripe_price_id',
        'nomba_plan_id',
        'is_active',
        'sort_order',
        'badge_color',
        'icon'
    ];

    protected $casts = [
        'features' => 'array',
        'price' => 'decimal:2',
        'is_active' => 'boolean'
    ];

    public function userSubscriptions()
    {
        return $this->hasMany(UserSubscription::class, 'subscription_id');
    }

    public function activeUserSubscriptions()
    {
        return $this->hasMany(UserSubscription::class, 'subscription_id')
                   ->where('status', 'active')
                   ->where('expires_at', '>', now());
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('price');
    }

    public function getFormattedPriceAttribute()
    {
        return '$' . number_format($this->price, 2);
    }
}
