<?php

namespace App\Helpers;

use App\Models\UserRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class UserRequestsHelper
{
    public static function insert($data)
    {
        $userData = Auth::user();
        $data['created_at'] = date('Y-m-d H:i:s');
        if ($userData) {
            $data['created_by'] = $userData->id;
        }

        $userRequest = new UserRequest($data);
        $userRequest->save();

        // Send notification
        if (isset($data['receiver_id']) && isset($data['sender_id'])) {
            NotificationHelper::sendConnectionRequestNotification(
                $data['receiver_id'],
                $data['sender_id']
            );
        }

        return $userRequest->id;
    }

    public static function update($data, $where)
    {
        $userData = Auth::user();
        $data['updated_at'] = date('Y-m-d H:i:s');
        if ($userData) {
            $data['updated_by'] = $userData->id;
        }
        return UserRequest::where($where)->update($data);
    }

    public static function getByCheckRequest($senderId, $receiverId)
    {
        return UserRequest::where('sender_id', $senderId)
                         ->where('receiver_id', $receiverId)
                         ->first();
    }

    public static function getSwipedUserIds($userId)
    {
        return UserRequest::where(function ($query) use ($userId) {
            $query->where('sender_id', $userId)
                  ->orWhere('receiver_id', $userId);
        })->pluck('sender_id', 'receiver_id')
          ->flatten()
          ->filter(function ($id) use ($userId) {
              return $id != $userId;
          })
          ->values()
          ->toArray();
    }

    public static function getPendingRequests($userId)
    {
        return UserRequest::where('receiver_id', $userId)
                         ->where('status', 'pending')
                         ->with(['sender' => function ($query) {
                             $query->select('id', 'name', 'username', 'profile', 'profile_url', 'bio');
                         }])
                         ->orderBy('created_at', 'desc')
                         ->get();
    }

    public static function getConnectedUsers($userId)
    {
        $connections = UserRequest::where(function ($query) use ($userId) {
                $query->where('sender_id', $userId)
                      ->orWhere('receiver_id', $userId);
            })
            ->where('status', 'accepted')
            ->where('sender_status', 'accepted')
            ->where('receiver_status', 'accepted')
            ->with(['sender', 'receiver'])
            ->get();

        return $connections->map(function ($connection) use ($userId) {
            return $connection->sender_id == $userId ? $connection->receiver : $connection->sender;
        });
    }

    public static function getConnectionCount($userId)
    {
        return UserRequest::where(function ($query) use ($userId) {
                $query->where('sender_id', $userId)
                      ->orWhere('receiver_id', $userId);
            })
            ->where('status', 'accepted')
            ->where('sender_status', 'accepted')
            ->where('receiver_status', 'accepted')
            ->count();
    }

    public static function acceptRequest($requestId, $userId)
    {
        $request = UserRequest::find($requestId);

        if (!$request || $request->receiver_id !== $userId) {
            return false;
        }

        $request->update([
            'status' => 'accepted',
            'receiver_status' => 'accepted',
            'sender_status' => 'accepted'
        ]);

        // Send notification to sender
        NotificationHelper::sendConnectionAcceptedNotification(
            $request->sender_id,
            $userId
        );

        return true;
    }

    public static function rejectRequest($requestId, $userId)
    {
        $request = UserRequest::find($requestId);

        if (!$request || $request->receiver_id !== $userId) {
            return false;
        }

        $request->update([
            'status' => 'rejected',
            'receiver_status' => 'rejected'
        ]);

        return true;
    }

    public static function disconnectUsers($userId, $targetUserId)
    {
        $connection = UserRequest::where(function ($query) use ($userId, $targetUserId) {
                $query->where(['sender_id' => $userId, 'receiver_id' => $targetUserId])
                      ->orWhere(['sender_id' => $targetUserId, 'receiver_id' => $userId]);
            })
            ->where('status', 'accepted')
            ->first();

        if ($connection) {
            $connection->update([
                'status' => 'disconnected',
                'sender_status' => 'disconnected',
                'receiver_status' => 'disconnected'
            ]);
            return true;
        }

        return false;
    }

    public static function getTodaySwipeCount($userId, $socialId = null)
    {
        $query = UserRequest::where('sender_id', $userId)
                           ->whereDate('created_at', Carbon::today());

        if ($socialId) {
            $query->where('social_id', $socialId);
        }

        return $query->count();
    }

    public static function getSwipeCountByType($userId, $type)
    {
        return UserRequest::where('sender_id', $userId)
                         ->where('request_type', $type)
                         ->whereDate('created_at', Carbon::today())
                         ->count();
    }

    public static function sendConnectionRequest($senderId, $receiverId, $socialId = null, $requestType = 'right_swipe', $message = null)
    {
        // Check if request already exists
        $existingRequest = self::getByCheckRequest($senderId, $receiverId);
        if ($existingRequest) {
            return ['success' => false, 'message' => 'Request already sent'];
        }

        // Check swipe limits
        if (!UserHelper::canUserSwipe($senderId)) {
            return ['success' => false, 'message' => 'Daily swipe limit reached'];
        }

        // Create request
        $requestData = [
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'social_id' => $socialId,
            'request_type' => $requestType,
            'message' => $message,
            'status' => 'pending'
        ];

        $requestId = self::insert($requestData);

        // Update swipe count
        $swipeType = $requestType === 'left_swipe' ? 'left' : 'right';
        UserHelper::incrementSwipeCount($senderId, $swipeType);

        return ['success' => true, 'request_id' => $requestId];
    }
}
