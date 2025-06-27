<?php
// app/Http/Controllers/API/V1/SearchController.php
namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Resources\V1\PostResource;
use App\Http\Resources\V1\UserResource;
use App\Http\Resources\V1\ConversationResource;
use App\Http\Resources\V1\MessageResource;
use App\Http\Resources\V1\SocialCircleResource;
use App\Http\Resources\V1\SubscriptionPlanResource;
use App\Models\Post;
use App\Models\User;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\SocialCircle;
use App\Models\Subscribe;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Stripe\Plan;

class SearchController extends BaseController
{
    /**
     * Search for users.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchUsers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error', $validator->errors(), 422);
        }

        $query = $request->query('query');

        $users = User::where(function($q) use ($query) {
            $q->where('name', 'like', "%{$query}%")
              ->orWhere('username', 'like', "%{$query}%")
              ->orWhere('email', 'like', "%{$query}%");
        })
        ->where('id', '!=', auth()->id())
        ->paginate(10);

        return $this->sendResponse('Users retrieved successfully', [
            'users' => UserResource::collection($users),
            'pagination' => [
                'total' => $users->total(),
                'count' => $users->count(),
                'per_page' => $users->perPage(),
                'current_page' => $users->currentPage(),
                'total_pages' => $users->lastPage(),
            ],
        ]);
    }

    /**
     * Search for posts.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchPosts(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error', $validator->errors(), 422);
        }

        $query = $request->query('query');
        $user = $request->user();

        // Get IDs of users the authenticated user is connected with
        // Fix the ambiguous column issue by specifying the table name for id
        $connectedUserIds = DB::table('users')
            ->join('user_requests', 'users.id', '=', 'user_requests.receiver_id')
            ->where('user_requests.sender_id', $user->id)
            ->where('user_requests.status', 'accepted')
            ->where('user_requests.sender_status', 'accepted')
            ->where('user_requests.receiver_status', 'accepted')
            ->whereNull('users.deleted_at')
            ->select('users.id') // Specify the table name for id
            ->pluck('users.id')  // Specify the table name for id
            ->toArray();

        // Also get users where the current user is the receiver
        $connectedUserIds2 = DB::table('users')
            ->join('user_requests', 'users.id', '=', 'user_requests.sender_id')
            ->where('user_requests.receiver_id', $user->id)
            ->where('user_requests.status', 'accepted')
            ->where('user_requests.sender_status', 'accepted')
            ->where('user_requests.receiver_status', 'accepted')
            ->whereNull('users.deleted_at')
            ->select('users.id') // Specify the table name for id
            ->pluck('users.id')  // Specify the table name for id
            ->toArray();

        // Merge both arrays and add the current user's ID
        $connectedUserIds = array_merge($connectedUserIds, $connectedUserIds2);
        $connectedUserIds[] = $user->id; // Include user's own posts
        $connectedUserIds = array_unique($connectedUserIds); // Remove duplicates

        $posts = Post::where('content', 'like', "%{$query}%")
            ->whereIn('user_id', $connectedUserIds)
            ->with('user', 'taggedUsers')
            ->latest()
            ->paginate(10);

        return $this->sendResponse('Posts retrieved successfully', [
            'posts' => PostResource::collection($posts),
            'pagination' => [
                'total' => $posts->total(),
                'count' => $posts->count(),
                'per_page' => $posts->perPage(),
                'current_page' => $posts->currentPage(),
                'total_pages' => $posts->lastPage(),
            ],
        ]);
    }

    /**
     * Search for conversations.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchConversations(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error', $validator->errors(), 422);
        }

        $query = $request->query('query');
        $user = $request->user();

        // Get conversations where the user is a participant and the name matches the query
        $conversations = Conversation::whereHas('participants', function($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->where('is_active', true);
            })
            ->where(function($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%");
            })
            ->with(['latestMessage', 'participants.user'])
            ->latest('last_message_at')
            ->paginate(10);

        return $this->sendResponse('Conversations retrieved successfully', [
            'conversations' => ConversationResource::collection($conversations),
            'pagination' => [
                'total' => $conversations->total(),
                'count' => $conversations->count(),
                'per_page' => $conversations->perPage(),
                'current_page' => $conversations->currentPage(),
                'total_pages' => $conversations->lastPage(),
            ],
        ]);
    }

    /**
     * Search for messages.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchMessages(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2',
            'conversation_id' => 'nullable|integer|exists:conversations,id',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error', $validator->errors(), 422);
        }

        $query = $request->query('query');
        $conversationId = $request->query('conversation_id');
        $user = $request->user();

        // Get messages in conversations where the user is a participant
        $messagesQuery = Message::whereHas('conversation.participants', function($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->where('is_active', true);
            })
            ->where('message', 'like', "%{$query}%")
            ->where('is_deleted', false);

        // Filter by conversation if specified
        if ($conversationId) {
            $messagesQuery->where('conversation_id', $conversationId);
        }

        $messages = $messagesQuery->with(['user', 'conversation'])
            ->latest()
            ->paginate(10);

        return $this->sendResponse('Messages retrieved successfully', [
            'messages' => MessageResource::collection($messages),
            'pagination' => [
                'total' => $messages->total(),
                'count' => $messages->count(),
                'per_page' => $messages->perPage(),
                'current_page' => $messages->currentPage(),
                'total_pages' => $messages->lastPage(),
            ],
        ]);
    }

    /**
     * Search for social circles.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchSocialCircles(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error', $validator->errors(), 422);
        }

        $query = $request->query('query');

        $socialCircles = SocialCircle::where('name', 'like', "%{$query}%")
            ->orWhere('description', 'like', "%{$query}%")
            ->paginate(10);

        return $this->sendResponse('Social circles retrieved successfully', [
            'social_circles' => SocialCircleResource::collection($socialCircles),
            'pagination' => [
                'total' => $socialCircles->total(),
                'count' => $socialCircles->count(),
                'per_page' => $socialCircles->perPage(),
                'current_page' => $socialCircles->currentPage(),
                'total_pages' => $socialCircles->lastPage(),
            ],
        ]);
    }

    /**
     * Search for subscription plans.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchSubscriptionPlans(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error', $validator->errors(), 422);
        }

        $query = $request->query('query');

        // Search for subscription plans in your database
        $plans = Subscribe::where('name', 'like', "%{$query}%")
            ->orWhere('description', 'like', "%{$query}%")
            ->paginate(10);

        return $this->sendResponse('Subscription plans retrieved successfully', [
            'plans' => SubscriptionPlanResource::collection($plans),
            'pagination' => [
                'total' => $plans->total(),
                'count' => $plans->count(),
                'per_page' => $plans->perPage(),
                'current_page' => $plans->currentPage(),
                'total_pages' => $plans->lastPage(),
            ],
        ]);
    }

    /**
     * Search across multiple content types.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchAll(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error', $validator->errors(), 422);
        }

        $query = $request->query('query');
        $user = $request->user();

        // Search users
        $users = User::where(function($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('username', 'like', "%{$query}%");
            })
            ->where('id', '!=', $user->id)
            ->limit(5)
            ->get();

        // Search posts
        $connectedUserIds = $this->getConnectedUserIds($user->id);
        $posts = Post::where('content', 'like', "%{$query}%")
            ->whereIn('user_id', $connectedUserIds)
            ->with('user')
            ->limit(5)
            ->latest()
            ->get();

        // Search conversations
        $conversations = Conversation::whereHas('participants', function($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->where('is_active', true);
            })
            ->where(function($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%");
            })
            ->with('latestMessage')
            ->limit(5)
            ->latest('last_message_at')
            ->get();

        return $this->sendResponse('Search results retrieved successfully', [
            'users' => UserResource::collection($users),
            'posts' => PostResource::collection($posts),
            'conversations' => ConversationResource::collection($conversations),
        ]);
    }
 /**
     * Discover users for the authenticated user.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function discoverUsers(Request $request)
    {
        $user = $request->user();

        // Get user's social circles
        $userSocialCircleIds = DB::table('user_social_circles')
            ->where('user_id', $user->id)
            ->pluck('social_circle_id')
            ->toArray();

        // Get IDs of users the authenticated user is already connected with
        $connectedUserIds = $this->getConnectedUserIds($user->id);

        // Get users who share social circles with the authenticated user
        // but are not yet connected
        $suggestedUsers = User::whereHas('socialCircles', function($query) use ($userSocialCircleIds) {
                $query->whereIn('social_circles.id', $userSocialCircleIds);
            })
            ->whereNotIn('id', $connectedUserIds) // Not already connected
            ->where('id', '!=', $user->id) // Not the current user
            ->where('deleted_flag', 'N') // Not deleted
            ->inRandomOrder() // Randomize results
            ->limit(20)
            ->get();

        return $this->sendResponse('Users discovered successfully', [
            'users' => UserResource::collection($suggestedUsers)
        ]);
    }

    /**
     * Discover trending posts.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function discoverTrendingPosts(Request $request)
    {
        $user = $request->user();

        // Get IDs of users the authenticated user is connected with
        $connectedUserIds = $this->getConnectedUserIds($user->id);

        // Get trending posts from connected users
        // Trending defined by most likes, comments, and shares in the last 7 days
        $trendingPosts = Post::whereIn('user_id', $connectedUserIds)
            ->where('created_at', '>=', now()->subDays(7))
            ->withCount(['likes', 'comments', 'shares'])
            ->orderByRaw('(likes_count + comments_count + shares_count) DESC')
            ->with('user', 'taggedUsers')
            ->limit(10)
            ->get();

        return $this->sendResponse('Trending posts retrieved successfully', [
            'posts' => PostResource::collection($trendingPosts)
        ]);
    }

    /**
     * Discover suggested connections.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function discoverSuggestedConnections(Request $request)
    {
        $user = $request->user();

        // Get IDs of users the authenticated user is already connected with
        $connectedUserIds = $this->getConnectedUserIds($user->id);

        // Get connections of connections (friends of friends)
        $connectionsOfConnections = DB::table('user_requests as ur1')
            ->join('user_requests as ur2', function($join) use ($connectedUserIds) {
                $join->on('ur1.receiver_id', '=', 'ur2.sender_id')
                    ->whereIn('ur1.sender_id', $connectedUserIds)
                    ->where('ur2.status', 'accepted')
                    ->where('ur2.sender_status', 'accepted')
                    ->where('ur2.receiver_status', 'accepted');
            })
            ->whereNotIn('ur2.receiver_id', $connectedUserIds)
            ->where('ur2.receiver_id', '!=', $user->id)
            ->select('ur2.receiver_id')
            ->distinct()
            ->pluck('receiver_id')
            ->toArray();

        // Get users who are connections of connections
        $suggestedUsers = User::whereIn('id', $connectionsOfConnections)
            ->where('deleted_flag', 'N')
            ->inRandomOrder()
            ->limit(10)
            ->get();

        return $this->sendResponse('Suggested connections retrieved successfully', [
            'users' => UserResource::collection($suggestedUsers)
        ]);
    }

    /**
     * Helper method to get connected user IDs.
     *
     * @param int $userId
     * @return array
     */
    private function getConnectedUserIds(int $userId): array
    {
        // Get users where the current user is the sender
        $senderConnections = DB::table('user_requests')
            ->where('sender_id', $userId)
            ->where('status', 'accepted')
            ->where('sender_status', 'accepted')
            ->where('receiver_status', 'accepted')
            ->pluck('receiver_id')
            ->toArray();

        // Get users where the current user is the receiver
        $receiverConnections = DB::table('user_requests')
            ->where('receiver_id', $userId)
            ->where('status', 'accepted')
            ->where('sender_status', 'accepted')
            ->where('receiver_status', 'accepted')
            ->pluck('sender_id')
            ->toArray();

        // Merge both arrays and add the current user's ID
        $connectedUserIds = array_merge($senderConnections, $receiverConnections);
        $connectedUserIds[] = $userId; // Include user's own posts/content

        return array_unique($connectedUserIds); // Remove duplicates
    }
}
