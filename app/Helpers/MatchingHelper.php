<?php

namespace App\Helpers;

use App\Models\User;
use App\Models\UserRequest;
use App\Models\UserLike;
use Illuminate\Support\Collection;

class MatchingHelper
{
    /**
     * Calculate compatibility score between two users
     */
    public static function calculateCompatibilityScore($userId1, $userId2): float
    {
        $user1 = User::with('socialCircles')->find($userId1);
        $user2 = User::with('socialCircles')->find($userId2);

        if (!$user1 || !$user2) {
            return 0.0;
        }

        $score = 0.0;
        $factors = 0;

        // Social circles compatibility (40% weight)
        $commonCircles = $user1->socialCircles->pluck('id')
                             ->intersect($user2->socialCircles->pluck('id'))
                             ->count();
        $totalCircles = $user1->socialCircles->count() + $user2->socialCircles->count();

        if ($totalCircles > 0) {
            $score += ($commonCircles / $totalCircles) * 0.4;
            $factors++;
        }

        // Location proximity (30% weight)
        if ($user1->country_id === $user2->country_id) {
            $score += 0.3;
            $factors++;
        }

        // Age compatibility (20% weight)
        if ($user1->birth_date && $user2->birth_date) {
            $age1 = $user1->birth_date->age;
            $age2 = $user2->birth_date->age;
            $ageDiff = abs($age1 - $age2);

            if ($ageDiff <= 5) {
                $score += 0.2;
            } elseif ($ageDiff <= 10) {
                $score += 0.1;
            }
            $factors++;
        }

        // Interests compatibility (10% weight)
        if ($user1->interests && $user2->interests) {
            $interests1 = is_array($user1->interests) ? $user1->interests : json_decode($user1->interests, true);
            $interests2 = is_array($user2->interests) ? $user2->interests : json_decode($user2->interests, true);

            if ($interests1 && $interests2) {
                $commonInterests = count(array_intersect($interests1, $interests2));
                $totalInterests = count(array_unique(array_merge($interests1, $interests2)));

                if ($totalInterests > 0) {
                    $score += ($commonInterests / $totalInterests) * 0.1;
                }
            }
            $factors++;
        }

        return $factors > 0 ? min($score, 1.0) : 0.0;
    }

    /**
     * Get personalized user recommendations
     */
    public static function getPersonalizedRecommendations($userId, $limit = 10): Collection
    {
        $user = User::with('socialCircles')->find($userId);
        if (!$user) {
            return collect();
        }

        // Get users with similar social circles
        $socialCircleIds = $user->socialCircles->pluck('id')->toArray();

        $candidates = User::where('id', '!=', $userId)
                         ->where('deleted_flag', 'N')
                         ->whereHas('socialCircles', function ($query) use ($socialCircleIds) {
                             $query->whereIn('social_id', $socialCircleIds);
                         })
                         ->get();

        // Filter out already swiped users
        $swipedUserIds = UserRequestsHelper::getSwipedUserIds($userId);
        $candidates = $candidates->whereNotIn('id', $swipedUserIds);

        // Filter out blocked users
        $blockedUserIds = BlockUserHelper::blockUserList($userId);
        $candidates = $candidates->whereNotIn('id', $blockedUserIds);

        // Calculate compatibility scores and sort
        $recommendations = $candidates->map(function ($candidate) use ($userId) {
            $candidate->compatibility_score = self::calculateCompatibilityScore($userId, $candidate->id);
            return $candidate;
        })
        ->sortByDesc('compatibility_score')
        ->take($limit);

        return $recommendations;
    }

    /**
     * Check if two users are a mutual match
     */
    public static function isMutualMatch($userId1, $userId2): bool
    {
        return UserLike::where('user_id', $userId1)
                      ->where('liked_user_id', $userId2)
                      ->where('is_active', true)
                      ->exists() &&
               UserLike::where('user_id', $userId2)
                      ->where('liked_user_id', $userId1)
                      ->where('is_active', true)
                      ->exists();
    }

    /**
     * Get trending users (most liked recently)
     */
    public static function getTrendingUsers($limit = 20): Collection
    {
        return User::withCount(['likesReceived' => function ($query) {
                    $query->where('created_at', '>=', now()->subDays(7))
                          ->where('is_active', true);
                }])
                   ->where('deleted_flag', 'N')
                   ->having('likes_received_count', '>', 0)
                   ->orderByDesc('likes_received_count')
                   ->limit($limit)
                   ->get();
    }
}
