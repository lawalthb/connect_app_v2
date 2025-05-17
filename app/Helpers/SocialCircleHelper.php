<?php

namespace App\Helpers;

use App\Models\User;
use App\Models\SocialCircle;
use App\Models\UserSocialCircle;

class SocialCircleHelper
{
    /**
     * Assign default social circles to a new user
     *
     * @param User $user
     * @return void
     */
    public static function assignDefaultCirclesToUser(User $user): void
    {
        // Get default social circle IDs
        // These could be configured or determined by your system
        $defaultCircles = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 23, 26, 27, 28, 29];

        foreach ($defaultCircles as $circleId) {
            UserSocialCircle::create([
                'user_id' => $user->id,
                'social_id' => $circleId
            ]);
        }
    }

    /**
     * Get all social circles as an array
     *
     * @return array
     */
    public static function getSocialArray(): array
    {
        $socialCircles = SocialCircle::all();
        $result = [];

        foreach ($socialCircles as $circle) {
            $result[$circle->id] = $circle->name;
        }

        return $result;
    }

    /**
     * Get social circles by user ID
     *
     * @param int $userId
     * @return array
     */
    public static function getByUserId(int $userId): array
    {
        return UserSocialCircle::where('user_id', $userId)
            ->where('deleted_flag', 'N')
            ->pluck('social_id')
            ->toArray();
    }
}
