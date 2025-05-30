<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\SendConnectionRequest;
use App\Http\Requests\V1\RespondToConnectionRequest;
use App\Http\Requests\V1\GetUsersByCircleRequest;
use App\Http\Resources\V1\UserProfileResource;
use App\Http\Resources\V1\ConnectionRequestResource;
use App\Http\Resources\V1\SwipeStatsResource;
use App\Helpers\UserHelper;
use App\Helpers\UserRequestsHelper;
use App\Helpers\UserLikeHelper;
use App\Models\User;
use App\Models\UserRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Helpers\Utility;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Connections",
 *     description="User connection and matching operations"
 * )
 */
class ConnectionController extends Controller
{
    private int $successStatus = 200;

    /**
     * @OA\Get(
     *     path="/api/v1/user",
     *     summary="Get current user details",
     *     tags={"Connections"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="User details retrieved successfully")
     * )
     */
    public function getUserDetails(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $userDetails = UserHelper::getAllDetailByUserId($user->id);

            return response()->json([
                'status' => 1,
                'message' => 'User details retrieved successfully',
                'data' => new UserProfileResource($userDetails)
            ], $this->successStatus);

        } catch (\Exception $e) {
            Log::error('Get user details failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 0,
                'message' => 'Failed to retrieve user details'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/user/{id}",
     *     summary="Get user details by ID",
     *     tags={"Connections"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="User details retrieved successfully")
     * )
     */
    public function getUserDetailsById(Request $request, $id)
    {
        try {
            $user = UserHelper::getAllDetailByUserId($id);

            if (!$user) {
                return response()->json([
                    'message' => 'User not found',
                    'status' => 0,
                    'data' => []
                ], 404);
            }

            $userData = Utility::convertString($user);

            return response()->json([
                'message' => 'Successfully!',
                'status' => 1,
                'data' => [$userData]
            ], $this->successStatus);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred: ' . $e->getMessage(),
                'status' => 0,
                'data' => []
            ], $this->successStatus);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/user/swipe-stats",
     *     summary="Get user swipe statistics",
     *     tags={"Connections"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Swipe stats retrieved successfully")
     * )
     */
    public function getSwipeStats(Request $request)
    {
        try {
            $auth = auth()->user();
            $swipeStats = UserHelper::getSwipeStats($auth->id);

            return response()->json([
                'message' => 'Successfully!',
                'status' => 1,
                'data' => $swipeStats
            ], $this->successStatus);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred: ' . $e->getMessage(),
                'status' => 0,
                'data' => []
            ], $this->successStatus);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/users/discover",
     *     summary="Get users for discovery/swiping by social circle",
     *     tags={"Connections"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="social_id", type="integer"),
     *             @OA\Property(property="country_id", type="integer"),
     *             @OA\Property(property="last_id", type="integer"),
     *             @OA\Property(property="limit", type="integer", default=10)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Discovery users retrieved successfully")
     * )
     */
    public function getUsersBySocialCircle(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'social_id' => 'required|array',
            'social_id.*' => 'integer',
            'country_id' => 'nullable|integer',
            'last_id' => 'nullable|integer',
            'limit' => 'nullable|integer|min:1|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'status' => 0,
                'data' => []
            ], $this->successStatus);
        }

        try {
            $socialIds = $request->input('social_id', []);
            $countryId = $request->input('country_id');
            $lastId = $request->input('last_id');
            $limit = $request->input('limit', 10);

            $getData = UserHelper::getSocialCircleWiseUser($socialIds, $lastId, $countryId, $limit);

            if (count($getData) != 0) {
                $getData = Utility::convertString($getData);
                return response()->json([
                    'message' => 'Successfully!',
                    'status' => 1,
                    'data' => $getData
                ], $this->successStatus);
            } else {
                return response()->json([
                    'message' => "No users available.",
                    'status' => 0,
                    'data' => []
                ], $this->successStatus);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred: ' . $e->getMessage(),
                'status' => 0,
                'data' => []
            ], $this->successStatus);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/connections/request",
     *     summary="Send connection request (swipe right)",
     *     tags={"Connections"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="user_id", type="integer"),
     *             @OA\Property(property="social_id", type="integer"),
     *             @OA\Property(property="request_type", type="string", enum={"right_swipe", "left_swipe", "super_like"}),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Connection request sent successfully")
     * )
     */
    public function sendRequest(SendConnectionRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $data = $request->validated();

            // Check if trying to send request to self
            if ($user->id == $data['user_id']) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Cannot send request to yourself'
                ], 400);
            }

            // Send connection request
            $result = UserRequestsHelper::sendConnectionRequest(
                $user->id,
                $data['user_id'],
                $data['social_id'] ?? null,
                $data['request_type'],
                $data['message'] ?? null
            );

            if (!$result['success']) {
                return response()->json([
                    'status' => 0,
                    'message' => $result['message']
                ], 400);
            }

            // Get updated swipe stats
            $swipeStats = UserHelper::getSwipeStats($user->id);

            return response()->json([
                'status' => 1,
                'message' => 'Connection request sent successfully',
                'data' => [
                    'request_id' => $result['request_id'],
                    'swipe_stats' => new SwipeStatsResource($swipeStats)
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Send connection request failed', [
                'user_id' => $request->user()->id,
                'target_user_id' => $data['user_id'] ?? null,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 0,
                'message' => 'Failed to send connection request'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/connections",
     *     summary="Get connected users",
     *     tags={"Connections"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Response(response=200, description="Connected users retrieved successfully")
     * )
     */
    public function getConnectedUsers(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $connectedUsers = UserHelper::getConnectedUsers($user->id);

            if ($connectedUsers->isEmpty()) {
                return response()->json([
                    'status' => 0,
                    'message' => 'No connections found',
                    'data' => []
                ], $this->successStatus);
            }

            return response()->json([
                'status' => 1,
                'message' => 'Connected users retrieved successfully',
                'data' => UserProfileResource::collection($connectedUsers)
            ], $this->successStatus);

        } catch (\Exception $e) {
            Log::error('Get connected users failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 0,
                'message' => 'Failed to retrieve connected users'
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/connections/request/{id}/respond",
     *     summary="Accept or reject connection request",
     *     tags={"Connections"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="action", type="string", enum={"accept", "reject", "block"})
     *         )
     *     ),
     *     @OA\Response(response=200, description="Request responded successfully")
     * )
     */
    public function respondToRequest(RespondToConnectionRequest $request, $id): JsonResponse
    {
        try {
            $user = $request->user();
            $data = $request->validated();

            $connectionRequest = UserRequest::find($id);

            if (!$connectionRequest) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Connection request not found'
                ], 404);
            }

            if ($connectionRequest->receiver_id !== $user->id) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Unauthorized to respond to this request'
                ], 403);
            }

            $success = false;
            $message = '';

            switch ($data['action']) {
                case 'accept':
                    $success = UserRequestsHelper::acceptRequest($id, $user->id);
                    $message = 'Connection request accepted successfully';
                    break;
                case 'reject':
                    $success = UserRequestsHelper::rejectRequest($id, $user->id);
                    $message = 'Connection request rejected successfully';
                    break;
                case 'block':
                    // Block user and reject request
                    BlockUserHelper::insert([
                        'user_id' => $user->id,
                        'block_user_id' => $connectionRequest->sender_id
                    ]);
                    $success = UserRequestsHelper::rejectRequest($id, $user->id);
                    $message = 'User blocked and request rejected';
                    break;
            }

            if (!$success) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Failed to respond to connection request'
                ], 400);
            }

            return response()->json([
                'status' => 1,
                'message' => $message,
                'data' => ['action' => $data['action']]
            ], $this->successStatus);

        } catch (\Exception $e) {
            Log::error('Respond to connection request failed', [
                'user_id' => $request->user()->id,
                'request_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 0,
                'message' => 'Failed to respond to connection request'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/connections/requests",
     *     summary="Get pending connection requests",
     *     tags={"Connections"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Connection requests retrieved successfully")
     * )
     */
    public function getConnectionRequests(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $requests = UserRequestsHelper::getPendingRequests($user->id);

            return response()->json([
                'status' => 1,
                'message' => 'Connection requests retrieved successfully',
                'data' => ConnectionRequestResource::collection($requests)
            ], $this->successStatus);

        } catch (\Exception $e) {
            Log::error('Get connection requests failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 0,
                'message' => 'Failed to retrieve connection requests'
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/connections/{id}/disconnect",
     *     summary="Disconnect from a user",
     *     tags={"Connections"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Disconnected successfully")
     * )
     */
    public function disconnect(Request $request, $id): JsonResponse
    {
        try {
            $user = $request->user();

            $success = UserRequestsHelper::disconnectUsers($user->id, $id);

            if (!$success) {
                return response()->json([
                    'status' => 0,
                    'message' => 'No connection found to disconnect'
                ], 404);
            }

            return response()->json([
                'status' => 1,
                'message' => 'Disconnected successfully'
            ], $this->successStatus);

        } catch (\Exception $e) {
            Log::error('Disconnect failed', [
                'user_id' => $request->user()->id,
                'target_user_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 0,
                'message' => 'Failed to disconnect'
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/users/{id}/like",
     *     summary="Like or unlike a user",
     *     tags={"Connections"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="type", type="string", enum={"profile", "photo", "super_like"}, default="profile")
     *         )
     *     ),
     *     @OA\Response(response=200, description="User liked/unliked successfully")
     * )
     */
    public function likeUser(Request $request, $id): JsonResponse
    {
        try {
            $user = $request->user();
            $type = $request->input('type', 'profile');

            if ($user->id == $id) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Cannot like yourself'
                ], 400);
            }

            $result = UserLikeHelper::toggleLike($user->id, $id, $type);

            return response()->json([
                'status' => 1,
                'message' => ucfirst($result) . ' successfully',
                'data' => ['action' => $result]
            ], $this->successStatus);

        } catch (\Exception $e) {
            Log::error('Like user failed', [
                'user_id' => $request->user()->id,
                'target_user_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 0,
                'message' => 'Failed to like user'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/user/stats",
     *     summary="Get user connection and like statistics",
     *     tags={"Connections"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="User stats retrieved successfully")
     * )
     */
    public function getUserStats(Request $request)
    {
        try {
            $auth = $request->user();
            $swipeStats = UserHelper::getSwipeStats($auth->id);
            $userSubscriptions = UserSubscriptionHelper::getByUserId($auth->id);

            $stats = [
                'user_id' => $auth->id,
                'swipe_stats' => $swipeStats,
                'subscriptions' => $userSubscriptions,
                'is_premium' => count($userSubscriptions) > 0
            ];

            return response()->json([
                'message' => 'Successfully!',
                'status' => 1,
                'data' => $stats
            ], $this->successStatus);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred: ' . $e->getMessage(),
                'status' => 0,
                'data' => []
            ], $this->successStatus);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/user/likes/received",
     *     summary="Get users who liked me",
     *     tags={"Connections"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Users who liked me retrieved successfully")
     * )
     */
    public function getUsersWhoLikedMe(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $usersWhoLikedMe = UserLikeHelper::getUsersWhoLikedMe($user->id);

            return response()->json([
                'status' => 1,
                'message' => 'Users who liked you retrieved successfully',
                'data' => UserProfileResource::collection($usersWhoLikedMe)
            ], $this->successStatus);

        } catch (\Exception $e) {
            Log::error('Get users who liked me failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 0,
                'message' => 'Failed to retrieve users who liked you'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/user/matches",
     *     summary="Get mutual likes (matches)",
     *     tags={"Connections"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Mutual matches retrieved successfully")
     * )
     */
    public function getMutualMatches(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $mutualMatches = UserLikeHelper::getMutualLikes($user->id);

            return response()->json([
                'status' => 1,
                'message' => 'Mutual matches retrieved successfully',
                'data' => UserProfileResource::collection($mutualMatches)
            ], $this->successStatus);

        } catch (\Exception $e) {
            Log::error('Get mutual matches failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 0,
                'message' => 'Failed to retrieve mutual matches'
            ], 500);
        }
    }

    /**
     * Helper method to get connection status between two users
     */
    private function getConnectionStatus($userId, $targetUserId): string
    {
        $request = UserRequestsHelper::getByCheckRequest($userId, $targetUserId);
        $reverseRequest = UserRequestsHelper::getByCheckRequest($targetUserId, $userId);

        if ($request) {
            return $request->status;
        } elseif ($reverseRequest) {
            return $reverseRequest->status === 'pending' ? 'received_request' : $reverseRequest->status;
        }

        return 'none';
    }
}
