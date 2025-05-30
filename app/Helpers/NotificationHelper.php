<?php

namespace App\Helpers;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class NotificationHelper
{
    // ... existing methods ...

    public static function sendConnectionRequestNotification($receiverId, $senderId)
    {
        $sender = User::find($senderId);
        if (!$sender) return;

        $notificationData = [
            'user_id' => $receiverId,
            'sender_id' => $senderId,
            'notification_title' => 'New Connection Request',
            'notification' => $sender->name . ' sent you a connection request',
            'notification_type' => 'connection_request',
            'object_id' => $senderId,
        ];

        $insert = self::insert($notificationData);

        // Send push notification
        $user = User::find($receiverId);
        if ($user && $user->device_token) {
            $pushData = [
                'title' => 'New Connection Request',
                'body' => $sender->name . ' wants to connect with you',
                'notification_type' => 'connection_request',
                'object_id' => $senderId
            ];

            self::pushToGoogle([$user->device_token], $pushData);
        }

        return $insert;
    }

    public static function sendConnectionAcceptedNotification($receiverId, $senderId)
    {
        $sender = User::find($senderId);
        if (!$sender) return;

        $notificationData = [
            'user_id' => $receiverId,
            'sender_id' => $senderId,
            'notification_title' => 'Connection Accepted',
            'notification' => $sender->name . ' accepted your connection request',
            'notification_type' => 'connection_accepted',
            'object_id' => $senderId,
        ];

        $insert = self::insert($notificationData);

        // Send push notification
        $user = User::find($receiverId);
        if ($user && $user->device_token) {
            $pushData = [
                'title' => 'Connection Accepted!',
                'body' => $sender->name . ' is now connected with you',
                'notification_type' => 'connection_accepted',
                'object_id' => $senderId
            ];

            self::pushToGoogle([$user->device_token], $pushData);
        }

        return $insert;
    }

    public static function sendUserLikeNotification($receiverId, $senderId)
    {
        $sender = User::find($senderId);
        if (!$sender) return;

        $notificationData = [
            'user_id' => $receiverId,
            'sender_id' => $senderId,
            'notification_title' => 'Someone Liked You',
            'notification' => $sender->name . ' liked your profile',
            'notification_type' => 'user_like',
            'object_id' => $senderId,
        ];

        $insert = self::insert($notificationData);

        // Send push notification
        $user = User::find($receiverId);
        if ($user && $user->device_token) {
            $pushData = [
                'title' => 'Someone Liked You!',
                'body' => $sender->name . ' liked your profile',
                'notification_type' => 'user_like',
                'object_id' => $senderId
            ];

            self::pushToGoogle([$user->device_token], $pushData);
        }

        return $insert;
    }

    public static function sendSuperLikeNotification($receiverId, $senderId)
    {
        $sender = User::find($senderId);
        if (!$sender) return;

        $notificationData = [
            'user_id' => $receiverId,
            'sender_id' => $senderId,
            'notification_title' => 'Super Like!',
            'notification' => $sender->name . ' super liked you!',
            'notification_type' => 'super_like',
            'object_id' => $senderId,
        ];

        $insert = self::insert($notificationData);

        // Send push notification
        $user = User::find($receiverId);
        if ($user && $user->device_token) {
            $pushData = [
                'title' => 'You Got a Super Like! ⭐',
                'body' => $sender->name . ' super liked you!',
                'notification_type' => 'super_like',
                'object_id' => $senderId
            ];

            self::pushToGoogle([$user->device_token], $pushData);
        }

        return $insert;
    }
}
