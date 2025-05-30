<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use App\Models\User;
use App\Helpers\UserSwipeHelper;
use App\Helpers\UserSubscriptionHelper;
use Carbon\Carbon;
use Auth;

class UserHelper
{
    public static function getById($id)
    {
        return User::where('id', $id)
                   ->where('deleted_flag', 'N')
                   ->first();
    }

    public static function getAllDetailByUserId($id)
    {
        $user = self::getById($id);
        if (!$user) return null;

        // Get profile data
        $allProfileData = ProfileMultiUploadHelper::getbyId($user->id);

        // Get stats
        $totalConnections = UserRequestsHelper::getConnectionCount($user->id);
        $totalLikes = UserLikeHelper::getReceivedLikesCount($user->id);
        $totalPosts = PostHelper::getTotalPostByUserId($user->id);

        // Get country info
        $countryData = CountryHelper::getById($user->country_id);

        // Add computed fields
        $user->total_connections = $totalConnections;
        $user->total_likes = $totalLikes;
        $user->total_posts = $totalPosts;
        $user->country_name = $countryData->country_name ?? '';
        $user->multiple_profile = $allProfileData;

        return $user;
    }

    public static function getDiscoveryUsers($currentUserId, $socialId = null, $limit = 20, $excludeIds = [])
    {
        $query = User::where('deleted_flag', 'N')
                    ->where('id', '!=', $currentUserId)
                    ->whereNotIn('id', $excludeIds);

        // Filter by social circle if provided
        if ($socialId) {
            $query->whereHas('socialCircles', function ($q) use ($socialId) {
                $q->where('social_id', $socialId);
            });
        }

        // Exclude already swiped users
        $swipedUserIds = UserRequestsHelper::getSwipedUserIds($currentUserId);
        if (!empty($swipedUserIds)) {
            $query->whereNotIn('id', $swipedUserIds);
        }

        // Exclude blocked users
        $blockedUserIds = BlockUserHelper::blockUserList($currentUserId);
        if (!empty($blockedUserIds)) {
            $query->whereNotIn('id', $blockedUserIds);
        }

        return $query->with(['profileImages', 'country'])
                    ->inRandomOrder()
                    ->limit($limit)
                    ->get();
    }

    public static function getSocialCircleWiseUsers($socialId, $currentUserId, $lastId = null, $countryId = null)
    {
        $query = User::where('deleted_flag', 'N')
                    ->where('id', '!=', $currentUserId);

        if ($socialId) {
            $query->whereHas('socialCircles', function ($q) use ($socialId) {
                $q->where('social_id', $socialId);
            });
        }

        if ($countryId) {
            $query->where('country_id', $countryId);
        }

        if ($lastId) {
            $query->where('id', '>', $lastId);
        }

        // Exclude already swiped users
        $swipedUserIds = UserRequestsHelper::getSwipedUserIds($currentUserId);
        if (!empty($swipedUserIds)) {
            $query->whereNotIn('id', $swipedUserIds);
        }

        return $query->with(['profileImages', 'country'])
                    ->orderBy('id')
                    ->limit(10)
                    ->get();
    }

    public static function getConnectedUsers($userId)
    {
        return UserRequestsHelper::getConnectedUsers($userId);
    }

    public static function update($data, $where)
    {
        $userData = Auth::user();
        $data['updated_at'] = date('Y-m-d H:i:s');
        if ($userData) {
            $data['updated_by'] = $userData->id;
        }
        return User::where($where)->update($data);
    }

    public static function insert($data)
    {
        $userData = Auth::user();
        $data['created_at'] = date('Y-m-d H:i:s');
        if ($userData) {
            $data['created_by'] = $userData->id;
        }
        $user = new User($data);
        $user->save();
        return $user->id;
    }

    /**
     * Check if user can swipe today
     */
    public static function canUserSwipe($userId)
    {
        $swipeStats = self::getSwipeStats($userId);
        return $swipeStats->total_swipes < $swipeStats->daily_limit;
    }

    /**
     * Get user's swipe statistics for today
     */
    public static function getSwipeStats($userId)
    {
        try {
            $user = User::find($userId);
            if (!$user) {
                return (object) [
                    'total_swipes' => 0,
                    'left_swipes' => 0,
                    'right_swipes' => 0,
                    'super_likes' => 0,
                    'daily_limit' => 50,
                    'remaining_swipes' => 50
                ];
            }

            // Get today's swipe record
            $todaySwipes = UserSwipeHelper::getTodayRecord($userId);

            if (!$todaySwipes) {
                $todaySwipes = (object) [
                    'total_swipes' => 0,
                    'left_swipes' => 0,
                    'right_swipes' => 0,
                    'super_likes' => 0
                ];
            }

            // Determine daily limit based on subscription
            $dailyLimit = self::getUserDailySwipeLimit($userId);

            return (object) [
                'total_swipes' => $todaySwipes->total_swipes,
                'left_swipes' => $todaySwipes->left_swipes,
                'right_swipes' => $todaySwipes->right_swipes,
                'super_likes' => $todaySwipes->super_likes,
                'daily_limit' => $dailyLimit,
                'remaining_swipes' => max(0, $dailyLimit - $todaySwipes->total_swipes)
            ];
        } catch (\Exception $e) {
            // Return default stats if there's an error
            return (object) [
                'total_swipes' => 0,
                'left_swipes' => 0,
                'right_swipes' => 0,
                'super_likes' => 0,
                'daily_limit' => 50,
                'remaining_swipes' => 50
            ];
        }
    }

    /**
     * Get user's daily swipe limit based on subscription
     */
    public static function getUserDailySwipeLimit($userId)
    {
        try {
            // Check user subscriptions
            $userSubscriptions = UserSubscriptionHelper::getByUserId($userId);

            // Unlimited subscription (subscription_id: 6)
            if (in_array('6', $userSubscriptions)) {
                return 999999; // Unlimited
            }

            // Premium subscription (subscription_id: 8)
            if (in_array('8', $userSubscriptions)) {
                return 100;
            }

            // Travel subscription (subscription_id: 4)
            if (in_array('4', $userSubscriptions)) {
                return 100;
            }

            // Free user
            return 50;
        } catch (\Exception $e) {
            // Default to free user limit if there's an error
            return 50;
        }
    }

    /**
     * Increment user's swipe count
     */
    public static function incrementSwipeCount($userId, $swipeType = 'right')
    {
        return UserSwipeHelper::incrementSwipe($userId, $swipeType);
    }

    /**
     * Get user's swipe history
     */
    public static function getSwipeHistory($userId, $days = 7)
    {
        return UserSwipe::where('user_id', $userId)
            ->where('swipe_date', '>=', Carbon::today()->subDays($days))
            ->orderBy('swipe_date', 'desc')
            ->get();
    }
}
