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
    Route::get('posts', [PostController::class, 'index']);
    Route::post('posts', [PostController::class, 'store']);
    Route::get('posts/{id}', [PostController::class, 'show']);
    Route::delete('posts/{id}', [PostController::class, 'destroy']);
    Route::get('user/posts', [PostController::class, 'userPosts']);
    Route::get('user/{id}/posts', [PostController::class, 'getUserPosts']);
    Route::get('feed', [PostController::class, 'feed']);

    // Post Likes
    Route::post('posts/{id}/like', [PostController::class, 'like']);
    Route::get('posts/{id}/likes', [PostController::class, 'getLikes']);

    // Comments
    Route::get('posts/{id}/comments', [CommentController::class, 'getPostComments']);
    Route::post('posts/{id}/comments', [CommentController::class, 'store']);
    Route::post('comments/{id}/reply', [CommentController::class, 'reply']);

    // Connections
    Route::post('connections/request', [ConnectionController::class, 'sendRequest']);
    Route::post('connections/request/{id}/respond', [ConnectionController::class, 'respondToRequest']);
    Route::post('connections/{id}/disconnect', [ConnectionController::class, 'disconnect']);
    Route::get('connections', [ConnectionController::class, 'getConnections']);
    Route::get('connections/requests', [ConnectionController::class, 'getIncomingRequests']);

    // Stories
    Route::post('stories', [StoryController::class, 'store']);
    Route::get('stories', [StoryController::class, 'userStories']);
    Route::get('stories/user/{id}', [StoryController::class, 'getUserStories']);
    Route::get('stories/feed', [StoryController::class, 'feed']);
    Route::delete('stories/{id}', [StoryController::class, 'destroy']);
    Route::post('stories/{id}/view', [StoryController::class, 'markAsViewed']);

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
        Route::post('initiate', [CallController::class, 'initiate']);
        Route::post('{call}/answer', [CallController::class, 'answer']);
        Route::post('{call}/end', [CallController::class, 'end']);
        Route::post('{call}/reject', [CallController::class, 'reject']);
        Route::get('conversation/{conversation}/history', [CallController::class, 'history']);
        Route::get('recent', [CallController::class, 'recentCalls']);
    });


});
