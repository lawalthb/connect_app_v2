<?php

namespace App\Helpers;

use App\Models\UserLike;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class UserLikeHelper
{
    public static function insert($data)
    {
        $userData = Auth::user();
        $data['created_at'] = date('Y-m-d H:i:s');

        $userLike = new UserLike($data);
        $userLike->save();

        // Send notification
        if (isset($data['liked_user_id']) && isset($data['user_id'])) {
            NotificationHelper::sendUserLikeNotification(
                $data['liked_user_id'],
                $data['user_id']
            );
        }

        return $userLike->id;
    }

    public static function toggleLike($userId, $likedUserId, $type = 'profile')
    {
        $existingLike = UserLike::where('user_id', $userId)
                               ->where('liked_user_id', $likedUserId)
                               ->where('type', $type)
                               ->first();

        if ($existingLike) {
            $existingLike->update(['is_active' => !$existingLike->is_active]);
            return $existingLike->is_active ? 'liked' : 'unliked';
        } else {
            self::insert([
                'user_id' => $userId,
                'liked_user_id' => $likedUserId,
                'type' => $type,
                'is_active' => true
            ]);
            return 'liked';
        }
    }

    public static function getReceivedLikesCount($userId)
    {
        return UserLike::where('liked_user_id', $userId)
                      ->where('is_active', true)
                      ->count();
    }

    public static function getGivenLikesCount($userId)
    {
        return UserLike::where('user_id', $userId)
                      ->where('is_active', true)
                      ->count();
    }

    public static function getUsersWhoLikedMe($userId, $limit = 20)
    {
        return UserLike::where('liked_user_id', $userId)
                      ->where('is_active', true)
                      ->with(['user' => function ($query) {
                          $query->select('id', 'name', 'username', 'profile', 'profile_url', 'bio');
                      }])
                      ->orderBy('created_at', 'desc')
                      ->limit($limit)
                      ->get()
                      ->pluck('user');
    }

    public static function hasUserLiked($userId, $likedUserId, $type = 'profile')
    {
        return UserLike::where('user_id', $userId)
                      ->where('liked_user_id', $likedUserId)
                      ->where('type', $type)
                      ->where('is_active', true)
                      ->exists();
    }

    public static function getMutualLikes($userId)
    {
        // Users who liked me and I liked back
        return UserLike::where('user_id', $userId)
                      ->where('is_active', true)
                      ->whereExists(function ($query) use ($userId) {
                          $query->select(DB::raw(1))
                                ->from('user_likes as ul2')
                                ->whereColumn('ul2.user_id', 'user_likes.liked_user_id')
                                ->where('ul2.liked_user_id', $userId)
                                ->where('ul2.is_active', true);
                      })
                      ->with(['likedUser' => function ($query) {
                          $query->select('id', 'name', 'username', 'profile', 'profile_url', 'bio');
                      }])
                      ->get()
                      ->pluck('likedUser');
    }
}
