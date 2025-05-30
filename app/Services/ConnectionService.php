<?php

namespace App\Services;

use App\Events\ConnectionRequestSent;
use App\Events\ConnectionAccepted;
use App\Helpers\UserRequestsHelper;
use App\Helpers\UserHelper;
use App\Helpers\NotificationHelper;
use App\Models\User;
use App\Models\UserRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConnectionService
{
    public function sendConnectionRequest(int $senderId, int $receiverId, array $data = []): array
    {
        try {
            DB::beginTransaction();

            // Validate users exist
            $sender = User::find($senderId);
            $receiver = User::find($receiverId);

            if (!$sender || !$receiver) {
                throw new \Exception('User not found');
            }

            // Check if users are the same
            if ($senderId === $receiverId) {
                throw new \Exception('Cannot send request to yourself');
            }

            // Check if request already exists
            $existingRequest = UserRequestsHelper::getByCheckRequest($senderId, $receiverId);
            if ($existingRequest) {
                throw new \Exception('Connection request already exists');
            }

            // Check swipe limits
            if (!UserHelper::canUserSwipe($senderId)) {
                throw new \Exception('Daily swipe limit reached');
            }

            // Create connection request
            $requestData = array_merge([
                'sender_id' => $senderId,
                'receiver_id' => $receiverId,
                'status' => 'pending',
                'request_type' => 'right_swipe'
            ], $data);

            $requestId = UserRequestsHelper::insert($requestData);
            $request = UserRequest::find($requestId);

            // Update swipe count
            $swipeType = ($data['request_type'] ?? 'right_swipe') === 'left_swipe' ? 'left' : 'right';
            UserHelper::incrementSwipeCount($senderId, $swipeType);

            // Fire event for real-time notifications
            if ($swipeType === 'right') {
                event(new ConnectionRequestSent($sender, $receiver, $request));
            }

            DB::commit();

            return [
                'success' => true,
                'request_id' => $requestId,
                'message' => 'Connection request sent successfully'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Connection request failed', [
                'sender_id' => $senderId,
                'receiver_id' => $receiverId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function acceptConnectionRequest(int $requestId, int $userId): array
    {
        try {
            DB::beginTransaction();

            $request = UserRequest::find($requestId);

            if (!$request) {
                throw new \Exception('Connection request not found');
            }

            if ($request->receiver_id !== $userId) {
                throw new \Exception('Unauthorized to accept this request');
            }

            if ($request->status !== 'pending') {
                throw new \Exception('Request is no longer pending');
            }

            // Accept the request
            $request->update([
                'status' => 'accepted',
                'sender_status' => 'accepted',
                'receiver_status' => 'accepted',
                'accepted_at' => now()
            ]);

            // Fire event for real-time notifications
            $sender = User::find($request->sender_id);
            $receiver = User::find($request->receiver_id);

            if ($sender && $receiver) {
                event(new ConnectionAccepted($sender, $receiver));
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Connection request accepted successfully'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Accept connection request failed', [
                'request_id' => $requestId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function getConnectionSuggestions(int $userId, int $limit = 10): array
    {
        try {
            $user = User::with('socialCircles')->find($userId);

            if (!$user) {
                throw new \Exception('User not found');
            }

            // Get users with common social circles
            $socialCircleIds = $user->socialCircles->pluck('id')->toArray();

            $suggestions = User::where('id', '!=', $userId)
                             ->where('deleted_flag', 'N')
                             ->whereHas('socialCircles', function ($query) use ($socialCircleIds) {
                                 $query->whereIn('social_id', $socialCircleIds);
                             })
                             ->whereNotIn('id', function ($query) use ($userId) {
                                 $query->select('receiver_id')
                                       ->from('user_requests')
                                       ->where('sender_id', $userId);
                             })
                             ->whereNotIn('id', function ($query) use ($userId) {
                                 $query->select('sender_id')
                                       ->from('user_requests')
                                       ->where('receiver_id', $userId);
                             })
                             ->inRandomOrder()
                             ->limit($limit)
                             ->get();

            return [
                'success' => true,
                'data' => $suggestions,
                'message' => 'Connection suggestions retrieved successfully'
            ];

        } catch (\Exception $e) {
            Log::error('Get connection suggestions failed', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
