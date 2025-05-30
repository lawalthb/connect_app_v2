<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use App\Models\UserSwipe;
use Carbon\Carbon;
use Auth;

class UserSwipeHelper
{
    public static function insert($data)
    {
        $user_data = Auth::user();
        $data['created_at'] = date('Y-m-d H:i:s');

        if ($user_data) {
            $data['created_by'] = $user_data->id;
        }

        $insert_id = new UserSwipe($data);
        $insert_id->save();
        $Insert = $insert_id->id;
        return $Insert;
    }

    public static function update($data, $where)
    {
        $user_data = Auth::user();
        $data['updated_at'] = date('Y-m-d H:i:s');

        if ($user_data) {
            $data['updated_by'] = $user_data->id;
        }

        $update = UserSwipe::where($where)->update($data);
        return $update;
    }

    public static function softDelete($data, $where)
    {
        $user_data = Auth::user();

        if ($user_data) {
            $data['deleted_by'] = $user_data->id;
            $data['deleted_at'] = date('Y-m-d H:i:s');
        }

        $update = UserSwipe::where($where)->update($data);
        return $update;
    }

    /**
     * Get today's swipe record for user
     */
    public static function getTodayRecord($userId)
    {
        return UserSwipe::where('user_id', $userId)
            ->where('swipe_date', Carbon::today())
            ->first();
    }

    /**
     * Get or create today's swipe record for user
     */
    public static function getOrCreateTodayRecord($userId)
    {
        $record = self::getTodayRecord($userId);

        if (!$record) {
            $data = [
                'user_id' => $userId,
                'swipe_date' => Carbon::today(),
                'left_swipes' => 0,
                'right_swipes' => 0,
                'super_likes' => 0,
                'total_swipes' => 0
            ];

            $recordId = self::insert($data);
            $record = UserSwipe::find($recordId);
        }

        return $record;
    }

    /**
     * Increment swipe count for user
     */
    public static function incrementSwipe($userId, $swipeType = 'right')
    {
        $record = self::getOrCreateTodayRecord($userId);

        $updateData = ['total_swipes' => $record->total_swipes + 1];

        switch ($swipeType) {
            case 'left':
                $updateData['left_swipes'] = $record->left_swipes + 1;
                break;
            case 'super':
                $updateData['super_likes'] = $record->super_likes + 1;
                $updateData['right_swipes'] = $record->right_swipes + 1;
                break;
            default: // right
                $updateData['right_swipes'] = $record->right_swipes + 1;
                break;
        }

        self::update($updateData, ['id' => $record->id]);

        return $record;
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

    /**
     * Get total swipes for today by user ID
     */
    public static function getTodayTotalSwipes($userId)
    {
        $record = self::getTodayRecord($userId);
        return $record ? $record->total_swipes : 0;
    }
}
