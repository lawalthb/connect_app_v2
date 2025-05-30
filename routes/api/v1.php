<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\V1\AuthController;
use App\Http\Controllers\API\V1\CallController;
use App\Http\Controllers\API\V1\UserController;
use App\Http\Controllers\API\V1\PostController;
use App\Http\Controllers\API\V1\CommentController;
use App\Http\Controllers\API\V1\SocialCircleController;
use App\Http\Controllers\API\V1\ConnectionController;
use App\Http\Controllers\API\V1\StoryController;
use App\Http\Controllers\API\V1\SearchController;
use App\Http\Controllers\API\V1\NotificationController;
use App\Http\Controllers\API\V1\SubscriptionController;
use App\Http\Controllers\API\V1\MessageController;
use App\Http\Controllers\API\V1\ConversationController;
use App\Http\Controllers\API\V1\ProfileController;

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

// Public routes
Route::post('login', [AuthController::class, 'login']);
Route::post('register', [AuthController::class, 'register'])
    ->middleware(['throttle:5,1']);
Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('verify-reset-otp', [AuthController::class, 'verifyResetOTP']);
Route::post('reset-password', [AuthController::class, 'resetPassword']);
Route::get('countries', [UserController::class, 'getCountries']);
Route::get('social-circles', [SocialCircleController::class, 'index']);
Route::post('verify-email', [AuthController::class, 'verifyEmail']);
Route::post('resend-verification-otp', [AuthController::class, 'resendVerificationOTP'])
    ->middleware(['throttle:5,1']);
Route::post('verify-email-otp', [AuthController::class, 'verifyEmailOTP'])
    ->middleware(['throttle:5,1']);

// Social login routes
Route::get('auth/{provider}', [AuthController::class, 'redirectToProvider']);
Route::get('auth/{provider}/callback', [AuthController::class, 'handleProviderCallback']);
Route::post('auth/{provider}/token', [AuthController::class, 'handleSocialLoginFromApp']);
Route::post('auth/{provider}/user-data', [AuthController::class, 'handleSocialLoginWithUserData']);
Route::get('timezones', [UserController::class, 'getTimezones']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('change-password', [AuthController::class, 'changePassword']);

    // User Profile
    Route::get('user', [UserController::class, 'show']);
    Route::get('user/{id}', [UserController::class, 'getUserById']);
    Route::post('profile', [ProfileController::class, 'update']);
    Route::post('profile/upload', [ProfileController::class, 'uploadProfilePicture']);
    Route::post('profile/upload-multiple', [ProfileController::class, 'uploadMultipleProfilePictures']);
    Route::delete('account', [ProfileController::class, 'deleteAccount']);
    Route::post('user/timezone', [UserController::class, 'updateTimezone']);

    // Social Links
    Route::get('social-links', [ProfileController::class, 'getSocialLinks']);
    Route::post('social-links', [ProfileController::class, 'update']);
    Route::delete('social-links', [ProfileController::class, 'deleteSocialLink']);

    // Social Circles
    Route::get('user/social-circles', [SocialCircleController::class, 'userSocialCircles']);
    Route::post('user/social-circles', [SocialCircleController::class, 'updateUserSocialCircles']);
    Route::get('user/{id}/social-circles', [SocialCircleController::class, 'getUserSocialCircles']);

    // Posts
    Route::prefix('posts')->group(function () {
        Route::get('/feed', [PostController::class, 'getFeed']);
        Route::get('/scheduled', [PostController::class, 'getScheduledPosts']);
        Route::get('/user/{userId?}', [PostController::class, 'getUserPosts']);
        Route::post('/', [PostController::class, 'store']);
        Route::get('/{post}', [PostController::class, 'show']);
        Route::put('/{post}', [PostController::class, 'update']);
        Route::delete('/{post}', [PostController::class, 'destroy']);

        // Post interactions
        Route::post('/{post}/react', [PostController::class, 'toggleReaction']);
        Route::post('/{post}/comments', [PostController::class, 'addComment']);
        Route::get('/{post}/comments', [PostController::class, 'getComments']);
        Route::post('/{post}/report', [PostController::class, 'reportPost']);
        Route::post('/{post}/share', [PostController::class, 'sharePost']);

        // Post management
        Route::post('/{post}/publish', [PostController::class, 'publishScheduledPost']);
        Route::get('/{post}/analytics', [PostController::class, 'getPostAnalytics']);
    });

    // Discovery & User Management
    Route::prefix('users')->group(function () {
        // User details and stats (no rate limit needed)
        Route::get('/{id}/details', [ConnectionController::class, 'getUserDetailsById']);
        Route::get('/stats', [ConnectionController::class, 'getUserStats']);
        Route::get('/swipe-stats', [ConnectionController::class, 'getSwipeStats']);

        // Discovery with rate limiting
        Route::middleware(['swipe.limit'])->group(function () {
            Route::post('/discover', [ConnectionController::class, 'getUsersBySocialCircle']);
        });

        // User likes (with rate limiting since these count as swipes)
        Route::middleware(['swipe.limit'])->group(function () {
            Route::post('/{id}/like', [ConnectionController::class, 'likeUser']);
        });

        // These don't need rate limiting
        Route::get('/likes/received', [ConnectionController::class, 'getUsersWhoLikedMe']);
        Route::get('/matches', [ConnectionController::class, 'getMutualMatches']);
    });

    // Connections
    Route::prefix('connections')->group(function () {
        // Connection requests with rate limiting (since these are swipes)
        Route::middleware(['swipe.limit'])->group(function () {
            Route::post('/request', [ConnectionController::class, 'sendRequest']);
        });

        // These don't need rate limiting
        Route::get('/requests', [ConnectionController::class, 'getIncomingRequests']);
        Route::post('/request/{id}/respond', [ConnectionController::class, 'respondToRequest']);
        Route::get('/', [ConnectionController::class, 'getConnections']);
        Route::post('/{id}/disconnect', [ConnectionController::class, 'disconnect']);
    });

    // Stories
    Route::prefix('stories')->group(function () {
        Route::post('/', [StoryController::class, 'store']);
        Route::get('/feed', [StoryController::class, 'feed']);
        Route::get('/my-stories', [StoryController::class, 'myStories']);
        Route::get('/archive', [StoryController::class, 'archive']);

        Route::prefix('{story}')->group(function () {
            Route::get('/', [StoryController::class, 'show']);
            Route::delete('/', [StoryController::class, 'destroy']);
            Route::post('/view', [StoryController::class, 'markAsViewed']);
            Route::get('/viewers', [StoryController::class, 'getViewers']);
            Route::post('/reply', [StoryController::class, 'reply']);
            Route::get('/replies', [StoryController::class, 'getReplies']);
        });
    });

    // User stories
    Route::get('users/{user}/stories', [StoryController::class, 'getUserStories']);

    // Search
    Route::get('search/users', [SearchController::class, 'searchUsers']);
    Route::get('search/posts', [SearchController::class, 'searchPosts']);
    Route::get('discover/users', [SearchController::class, 'discoverUsers']);

    // Notifications
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    // Subscriptions
    Route::get('subscriptions', [SubscriptionController::class, 'index']);
    Route::post('subscriptions', [SubscriptionController::class, 'subscribe']);
    Route::post('subscriptions/{id}/cancel', [SubscriptionController::class, 'cancel']);
    Route::get('subscriptions/restore', [SubscriptionController::class, 'restore']);
    Route::post('subscriptions/boost', [SubscriptionController::class, 'activateBoost']);

    // Messaging Routes
    Route::prefix('conversations')->group(function () {
        // Conversation management
        Route::get('/', [ConversationController::class, 'index']);
        Route::post('/', [ConversationController::class, 'store']);
        Route::get('/{id}', [ConversationController::class, 'show']);
        Route::post('/{id}/leave', [ConversationController::class, 'leave']);

        // Messages within conversations
        Route::get('/{id}/messages', [MessageController::class, 'index']);
        Route::post('/{id}/messages', [MessageController::class, 'store']);
        Route::post('/{id}/messages/read', [MessageController::class, 'markAsRead']);
        Route::delete('/{id}/messages/{messageId}', [MessageController::class, 'destroy']);
    });

    // Direct messaging shortcuts
    Route::prefix('messages')->group(function () {
        Route::get('/', [ConversationController::class, 'index']); // Alias for conversations
        Route::post('/send', [MessageController::class, 'sendDirectMessage']); // For quick messaging
    });

    // Call routes
    Route::prefix('calls')->group(function () {
        // Call management
        Route::post('initiate', [CallController::class, 'initiate']);
        Route::post('{call}/answer', [CallController::class, 'answer']);
        Route::post('{call}/reject', [CallController::class, 'reject']);
        Route::post('{call}/end', [CallController::class, 'end']);

        // Call history
        Route::get('history', [CallController::class, 'getUserCallHistory']);
        Route::get('conversation/{conversation}/history', [CallController::class, 'getConversationCallHistory']);

        // Call participants
        Route::get('{call}/participants', [CallController::class, 'getCallParticipants']);
        Route::post('{call}/participants/{user}/kick', [CallController::class, 'kickParticipant']);
    });

    // Test service - Remove this in production
    Route::get('test-agora', function () {
        return \App\Helpers\AgoraHelper::testTokenGeneration();
    });

    Route::get('test-agora-user/{userId}', function ($userId) {
        return \App\Helpers\AgoraHelper::generateTokenForUser(
            $userId,
            'test_channel_' . time(),
            3600,
            'publisher'
        );
    });
});
