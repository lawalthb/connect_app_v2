<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class UserSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subscription_id',
        'amount',
        'currency',
        'payment_method',
        'payment_status',
        'transaction_reference',
        'customer_id',
        'payment_details',
        'started_at',
        'expires_at',
        'cancelled_at',
        'status',
        'parent_id',
        'boost_count',
        'auto_renew',
        'created_by',
        'updated_by',
        'deleted_flag'
    ];

    protected $casts = [
        'payment_details' => 'array',
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'amount' => 'decimal:2'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscribe::class, 'subscription_id');
    }

    public function parent()
    {
        return $this->belongsTo(UserSubscription::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(UserSubscription::class, 'parent_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                    ->where('deleted_flag', 'N')
                    ->where('expires_at', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now())
                    ->whereIn('status', ['active', 'expired']);
    }

    public function isActive()
    {
        return $this->status === 'active' &&
               $this->deleted_flag === 'N' &&
               $this->expires_at > now();
    }

    public function isExpired()
    {
        return $this->expires_at <= now();
    }

    public function hasFeature($feature)
    {
        if (!$this->isActive()) {
            return false;
        }

        $subscription = $this->subscription;
        if (!$subscription || !$subscription->features) {
            return false;
        }

        return in_array($feature, $subscription->features);
    }
}
