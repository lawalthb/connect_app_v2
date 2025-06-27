<?php

namespace App\Helpers;

use App\Models\UserSubscription;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UserSubscriptionHelper
{
    public static function insert($data)
    {
        $user_data = Auth::user();
        $data['created_at'] = date('Y-m-d H:i:s');

        // Set expiration based on subscription type
        if (isset($data['subscription_id'])) {
            if ($data['subscription_id'] == 4) { // Connect Boost - 1 day
                $data['expires_at'] = date('Y-m-d H:i:s', strtotime('+1 days', strtotime($data['created_at'])));
            } else { // Other subscriptions - 30 days
                $data['expires_at'] = date('Y-m-d H:i:s', strtotime('+30 days', strtotime($data['created_at'])));
            }
        }

        // Set start date
        $data['started_at'] = $data['created_at'];

        if ($user_data) {
            $data['created_by'] = $user_data->id;
        }

        $insert_id = new UserSubscription($data);
        $insert_id->save();

        return $insert_id->id;
    }

    public static function update($data, $where)
    {
        $user_data = Auth::user();
        $data['updated_at'] = date('Y-m-d H:i:s');

        if ($user_data) {
            $data['updated_by'] = $user_data->id;
        }

        return UserSubscription::where($where)->update($data);
    }

    public static function softDelete($data, $where)
    {
        $user_data = Auth::user();

        if ($user_data) {
            $data['deleted_by'] = $user_data->id;
            $data['deleted_at'] = date('Y-m-d H:i:s');
        }

        return UserSubscription::where($where)->update($data);
    }

    /**
     * Get active subscription IDs for user
     */
    public static function getByUserId($userId)
    {
        $subscriptions = UserSubscription::where('user_id', $userId)
            ->where('expires_at', '>', Carbon::now())
            ->where('deleted_flag', 'N')
            ->where('status', 'active')
            ->pluck('subscription_id')
            ->toArray();

        return array_map('strval', $subscriptions);
    }

    /**
     * Get all active subscriptions for user with details
     */
    public static function getActiveSubscriptionsWithDetails($userId)
    {
        return DB::table('user_subscriptions as us')
            ->join('subscribes as s', 'us.subscription_id', '=', 's.id')
            ->where('us.user_id', $userId)
            ->where('us.status', 'active')
            ->where('us.expires_at', '>', now())
            ->select([
                'us.*',
                's.name as subscription_name',
                's.slug',
                's.description',
                's.features'
            ])
            ->get();
    }

    /**
     * Check if user has specific subscription
     */
    public static function hasSubscription($userId, $subscriptionId)
    {
        return UserSubscription::where('user_id', $userId)
            ->where('subscription_id', $subscriptionId)
            ->where('expires_at', '>', Carbon::now())
            ->where('deleted_flag', 'N')
            ->where('status', 'active')
            ->exists();
    }

    /**
     * Get premium subscription by user ID
     */
    public static function getPremiumByUserId($userId, $subscriptionId)
    {
        return UserSubscription::where('user_id', $userId)
            ->where('subscription_id', $subscriptionId)
            ->where('expires_at', '>', Carbon::now())
            ->where('deleted_flag', 'N')
            ->where('status', 'active')
            ->first();
    }

    /**
     * Check if user has active subscription
     */
    public static function getActiveByUserId($userId)
    {
        return UserSubscription::where('user_id', $userId)
            ->where('expires_at', '>', Carbon::now())
            ->where('deleted_flag', 'N')
            ->where('status', 'active')
            ->exists();
    }

    /**
     * Get boost usage count for premium users
     */
    public static function getCheckBoostPremium($premiumSubscriptionId)
    {
        return UserSubscription::where('parent_id', $premiumSubscriptionId)
            ->where('subscription_id', 4) // Boost subscription ID
            ->where('status', 'active')
            ->whereMonth('created_at', date('m'))
            ->whereYear('created_at', date('Y'))
            ->count();
    }

    /**
     * Check if user has unlimited feature
     */
    public static function hasUnlimitedAccess($userId)
    {
        $activeSubscriptions = self::getByUserId($userId);

        // Check for unlimited or premium subscriptions
        return in_array('6', $activeSubscriptions) || in_array('8', $activeSubscriptions);
    }

    /**
     * Check if user has travel feature
     */
    public static function hasTravelAccess($userId)
    {
        $activeSubscriptions = self::getByUserId($userId);

        // Check for travel or premium subscriptions
        return in_array('4', $activeSubscriptions) || in_array('8', $activeSubscriptions);
    }

    /**
     * Check if user has boost feature
     */
    public static function hasBoostAccess($userId)
    {
        $subscriptions = self::getByUserId($userId);
        return in_array('4', $subscriptions) || in_array('3', $subscriptions); // Boost or Premium
    }

    /**
     * Expire old subscriptions
     */
    public static function expireOldSubscriptions()
    {
        return UserSubscription::where('expires_at', '<=', Carbon::now())
            ->where('status', 'active')
            ->update(['status' => 'expired']);
    }

    /**
     * Cancel subscription
     */
    public static function cancelSubscription($userId, $subscriptionId)
    {
        return UserSubscription::where('user_id', $userId)
            ->where('subscription_id', $subscriptionId)
            ->where('status', 'active')
            ->update([
                'status' => 'cancelled',
                'cancelled_at' => Carbon::now()
            ]);
    }

    /**
     * Restore subscription for iOS restore purchases
     */
    public static function getPremiumByUserIdRestore($userId)
    {
        // This method can be used to restore purchases on iOS
        return UserSubscription::where('user_id', $userId)
            ->where('deleted_flag', 'N')
            ->get();
    }

    /**
     * Check already unsubscribed
     */
    public static function chackAlreadyUnsubscription($data)
    {
        $query = UserSubscription::where('user_id', Auth::id())
            ->where('subscription_id', $data['subscription_id'])
            ->where('payment_method', $data['payment_type'])
            ->where('status', 'active');

        if (isset($data['stripe_subscription_id'])) {
            $query->where('transaction_reference', $data['stripe_subscription_id']);
        }

        return $query->first();
    }

    /**
     * Delete PayStack/Nomba subscription
     */
    public static function deletePayStackSubscription($data)
    {
        $query = UserSubscription::where('user_id', Auth::id())
            ->where('subscription_id', $data['subscription_id'])
            ->where('payment_method', $data['payment_type']);

        if (isset($data['stripe_subscription_id'])) {
            $query->where('transaction_reference', $data['stripe_subscription_id']);
        }

        return $query->update([
            'status' => 'cancelled',
            'cancelled_at' => Carbon::now()
        ]);
    }
}
