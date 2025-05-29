<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\V1\StorePostRequest;
use App\Http\Requests\V1\UpdatePostRequest;
use App\Http\Requests\V1\StoreCommentRequest;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\PostComment;
use App\Models\PostReport;
use App\Models\PostView;
use App\Models\PostShare;
use App\Services\MediaProcessingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PostController extends BaseController
{
    protected $successStatus = 200;
    protected $mediaService;

    public function __construct(MediaProcessingService $mediaService)
    {
        $this->mediaService = $mediaService;
    }

    /**
     * Get feed posts for user's social circles
     */
    public function getFeed(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 20);

            // Get user's social circles
            $userCircles = $user->socialCircles()->pluck('social_circles.id');

            $posts = Post::with([
                'user:id,name,username,profile,profile_url',
                'socialCircle:id,name,color',
                'media',
                'taggedUsers:id,name,username',
                'likes' => function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                }
            ])
            ->whereIn('social_circle_id', $userCircles)
            ->published()
            ->latest('published_at')
            ->paginate($perPage);

            // Add reaction counts and user interaction data
            $posts->getCollection()->transform(function ($post) use ($user) {
                $post->reaction_counts = $post->getReactionCounts();
                $post->user_reaction = $post->getUserReaction($user->id);
                $post->has_user_liked = $post->hasUserLiked($user->id);
                return $post;
            });

            return response()->json([
                'status' => 1,
                'message' => 'Feed retrieved successfully',
                'data' => $posts
            ], $this->successStatus);

        } catch (\Exception $e) {
            Log::error('Feed retrieval failed', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => 0,
                'message' => 'Failed to retrieve feed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new post
     */
    public function store(StorePostRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $user = auth()->user();

            // Create post
            $post = Post::create([
                'user_id' => $user->id,
                'social_circle_id' => $request->social_circle_id,
                'content' => $request->content,
                'type' => $request->type,
                'location' => $request->location,
                'published_at' => $request->scheduled_at ? null : now(),
                'scheduled_at' => $request->scheduled_at,
                'is_published' => !$request->scheduled_at,
            ]);

            // Process and upload media files
            if ($request->hasFile('media')) {
                foreach ($request->file('media') as $index => $file) {
                    $mediaData = $this->mediaService->processMedia($file, $post->id);

                    $post->media()->create([
                        'type' => $mediaData['type'],
                        'file_path' => $mediaData['file_path'],
                        'file_url' => $mediaData['file_url'],
                        'original_name' => $mediaData['original_name'],
                        'file_size' => $mediaData['file_size'],
                        'mime_type' => $mediaData['mime_type'],
                        'width' => $mediaData['width'] ?? null,
                        'height' => $mediaData['height'] ?? null,
                        'duration' => $mediaData['duration'] ?? null,
                        'thumbnail_path' => $mediaData['thumbnail_path'] ?? null,
                        'thumbnail_url' => $mediaData['thumbnail_url'] ?? null,
                        'compressed_versions' => $mediaData['compressed_versions'] ?? null,
                        'order' => $index + 1,
                    ]);
                }
            }

            // Tag users
            if ($request->tagged_users) {
                foreach ($request->tagged_users as $taggedUserId) {
                    $post->taggedUsers()->attach($taggedUserId, [
                        'tagged_by' => $user->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::commit();

            // Load relationships for response
            $post->load([
                'user:id,name,username,profile,profile_url',
                'socialCircle:id,name,color',
                'media',
                'taggedUsers:id,name,username'
            ]);

            return response()->json([
                'status' => 1,
                'message' => $request->scheduled_at ? 'Post scheduled successfully' : 'Post created successfully',
                'data' => $post
            ], $this->successStatus);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Post creation failed', ['error' => $e->getMessage(), 'user_id' => auth()->id()]);

            return response()->json([
                'status' => 0,
                'message' => 'Failed to create post',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific post
     */
    public function show(Post $post): JsonResponse
    {
        try {
            $user = auth()->user();

            // Record view
            $this->recordView($post, $user);

            $post->load([
                'user:id,name,username,profile,profile_url',
                'socialCircle:id,name,color',
                'media',
                'taggedUsers:id,name,username',
                'comments.user:id,name,username,profile,profile_url',
                'comments.replies.user:id,name,username,profile,profile_url'
            ]);

            // Add user interaction data
            $post->reaction_counts = $post->getReactionCounts();
            $post->user_reaction = $post->getUserReaction($user->id);
            $post->has_user_liked = $post->hasUserLiked($user->id);

            return response()->json([
                'status' => 1,
                'message' => 'Post retrieved successfully',
                'data' => $post
            ], $this->successStatus);

        } catch (\Exception $e) {
            Log::error('Post retrieval failed', ['post_id' => $post->id, 'error' => $e->getMessage()]);
            return response()->json([
                'status' => 0,
                'message' => 'Failed to retrieve post'
            ], 500);
        }
    }

    /**
     * Update post (same day only)
     */
    public function update(UpdatePostRequest $request, Post $post): JsonResponse
    {
        try {
            $post->update($request->validated());
            $post->markAsEdited();

            return response()->json([
                'status' => 1,
                'message' => 'Post updated successfully',
                'data' => $post
            ], $this->successStatus);

        } catch (\Exception $e) {
            Log::error('Post update failed', ['post_id' => $post->id, 'error' => $e->getMessage()]);
            return response()->json([
                'status' => 0,
                'message' => 'Failed to update post'
            ], 500);
        }
    }

    /**
     * Delete post
     */
    public function destroy(Post $post): JsonResponse
    {
        try {
            if ($post->user_id !== auth()->id()) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Unauthorized to delete this post'
                ], 403);
            }

            // Delete media files from S3
            $mediaFiles = $post->media;
            foreach ($mediaFiles as $media) {
                $media->delete(); // This will trigger S3 deletion in model
            }

            $post->delete();

            return response()->json([
                'status' => 1,
                'message' => 'Post deleted successfully'
            ], $this->successStatus);

        } catch (\Exception $e) {
            Log::error('Post deletion failed', ['post_id' => $post->id, 'error' => $e->getMessage()]);
            return response()->json([
                'status' => 0,
                'message' => 'Failed to delete post'
            ], 500);
        }
    }

    /**
     * Like/Unlike or React to post
     */
    public function toggleReaction(Request $request, Post $post): JsonResponse
    {
        $request->validate([
            'reaction_type' => 'required|in:like,love,laugh,angry,sad,wow'
        ]);

        try {
            $user = auth()->user();
            $reactionType = $request->reaction_type;

            $existingReaction = PostLike::where('post_id', $post->id)
                                      ->where('user_id', $user->id)
                                      ->first();

                                      if ($existingReaction) {
                                        if ($existingReaction->reaction_type === $reactionType) {
                                            // Remove reaction
                                            $existingReaction->delete();
                                            $action = 'removed';
                                        } else {
                                            // Update reaction type
                                            $existingReaction->update(['reaction_type' => $reactionType]);
                                            $action = 'updated';
                                        }
                                    } else {
                                        // Create new reaction
                                        PostLike::create([
                                            'post_id' => $post->id,
                                            'user_id' => $user->id,
                                            'reaction_type' => $reactionType
                                        ]);
                                        $action = 'added';
                                    }

                                    // Get updated reaction counts
                                    $reactionCounts = $post->fresh()->getReactionCounts();
                                    $userReaction = $post->getUserReaction($user->id);

                                    return response()->json([
                                        'status' => 1,
                                        'message' => "Reaction {$action} successfully",
                                        'data' => [
                                            'action' => $action,
                                            'reaction_counts' => $reactionCounts,
                                            'user_reaction' => $userReaction,
                                            'total_likes' => $post->fresh()->likes_count
                                        ]
                                    ], $this->successStatus);

                                } catch (\Exception $e) {
                                    Log::error('Reaction toggle failed', ['post_id' => $post->id, 'error' => $e->getMessage()]);
                                    return response()->json([
                                        'status' => 0,
                                        'message' => 'Failed to toggle reaction'
                                    ], 500);
                                }
                            }

                            /**
                             * Add comment to post
                             */
                            public function addComment(StoreCommentRequest $request, Post $post): JsonResponse
                            {
                                try {
                                    $comment = $post->comments()->create([
                                        'user_id' => auth()->id(),
                                        'parent_id' => $request->parent_id,
                                        'content' => $request->content,
                                    ]);

                                    $comment->load('user:id,name,username,profile,profile_url');

                                    return response()->json([
                                        'status' => 1,
                                        'message' => 'Comment added successfully',
                                        'data' => $comment
                                    ], $this->successStatus);

                                } catch (\Exception $e) {
                                    Log::error('Comment creation failed', ['post_id' => $post->id, 'error' => $e->getMessage()]);
                                    return response()->json([
                                        'status' => 0,
                                        'message' => 'Failed to add comment'
                                    ], 500);
                                }
                            }

                            /**
                             * Get comments for a post
                             */
                            public function getComments(Request $request, Post $post): JsonResponse
                            {
                                try {
                                    $perPage = $request->get('per_page', 20);

                                    $comments = $post->comments()
                                                   ->with([
                                                       'user:id,name,username,profile,profile_url',
                                                       'replies.user:id,name,username,profile,profile_url'
                                                   ])
                                                   ->latest()
                                                   ->paginate($perPage);

                                    return response()->json([
                                        'status' => 1,
                                        'message' => 'Comments retrieved successfully',
                                        'data' => $comments
                                    ], $this->successStatus);

                                } catch (\Exception $e) {
                                    Log::error('Comments retrieval failed', ['post_id' => $post->id, 'error' => $e->getMessage()]);
                                    return response()->json([
                                        'status' => 0,
                                        'message' => 'Failed to retrieve comments'
                                    ], 500);
                                }
                            }

                            /**
                             * Report a post
                             */
                            public function reportPost(Request $request, Post $post): JsonResponse
                            {
                                $request->validate([
                                    'reason' => 'required|in:spam,inappropriate_content,harassment,hate_speech,violence,false_information,copyright_violation,other',
                                    'description' => 'nullable|string|max:1000'
                                ]);

                                try {
                                    // Check if user already reported this post
                                    $existingReport = PostReport::where('post_id', $post->id)
                                                               ->where('reported_by', auth()->id())
                                                               ->first();

                                    if ($existingReport) {
                                        return response()->json([
                                            'status' => 0,
                                            'message' => 'You have already reported this post'
                                        ], 400);
                                    }

                                    PostReport::create([
                                        'post_id' => $post->id,
                                        'reported_by' => auth()->id(),
                                        'reason' => $request->reason,
                                        'description' => $request->description,
                                    ]);

                                    return response()->json([
                                        'status' => 1,
                                        'message' => 'Post reported successfully'
                                    ], $this->successStatus);

                                } catch (\Exception $e) {
                                    Log::error('Post report failed', ['post_id' => $post->id, 'error' => $e->getMessage()]);
                                    return response()->json([
                                        'status' => 0,
                                        'message' => 'Failed to report post'
                                    ], 500);
                                }
                            }

                            /**
                             * Share a post
                             */
                            public function sharePost(Request $request, Post $post): JsonResponse
                            {
                                $request->validate([
                                    'shared_to' => 'required|in:external_social_media,direct_message,email,link_copy,other',
                                    'platform' => 'nullable|string'
                                ]);

                                try {
                                    PostShare::create([
                                        'post_id' => $post->id,
                                        'user_id' => auth()->id(),
                                        'shared_to' => $request->shared_to,
                                        'platform' => $request->platform,
                                    ]);

                                    return response()->json([
                                        'status' => 1,
                                        'message' => 'Post shared successfully',
                                        'data' => [
                                            'shares_count' => $post->fresh()->shares_count
                                        ]
                                    ], $this->successStatus);

                                } catch (\Exception $e) {
                                    Log::error('Post share failed', ['post_id' => $post->id, 'error' => $e->getMessage()]);
                                    return response()->json([
                                        'status' => 0,
                                        'message' => 'Failed to share post'
                                    ], 500);
                                }
                            }

                            /**
                             * Get user's own posts
                             */
                            public function getUserPosts(Request $request, $userId = null): JsonResponse
                            {
                                try {
                                    $targetUserId = $userId ?? auth()->id();
                                    $page = $request->get('page', 1);
                                    $perPage = $request->get('per_page', 20);

                                    $posts = Post::with([
                                        'user:id,name,username,profile,profile_url',
                                        'socialCircle:id,name,color',
                                        'media',
                                        'taggedUsers:id,name,username'
                                    ])
                                    ->where('user_id', $targetUserId)
                                    ->published()
                                    ->latest('published_at')
                                    ->paginate($perPage);

                                    return response()->json([
                                        'status' => 1,
                                        'message' => 'Posts retrieved successfully',
                                        'data' => $posts
                                    ], $this->successStatus);

                                } catch (\Exception $e) {
                                    Log::error('User posts retrieval failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
                                    return response()->json([
                                        'status' => 0,
                                        'message' => 'Failed to retrieve posts'
                                    ], 500);
                                }
                            }

                            /**
                             * Get scheduled posts (user's own only)
                             */
                            public function getScheduledPosts(Request $request): JsonResponse
                            {
                                try {
                                    $posts = Post::with([
                                        'socialCircle:id,name,color',
                                        'media'
                                    ])
                                    ->where('user_id', auth()->id())
                                    ->scheduled()
                                    ->orderBy('scheduled_at')
                                    ->get();

                                    return response()->json([
                                        'status' => 1,
                                        'message' => 'Scheduled posts retrieved successfully',
                                        'data' => $posts
                                    ], $this->successStatus);

                                } catch (\Exception $e) {
                                    Log::error('Scheduled posts retrieval failed', ['error' => $e->getMessage()]);
                                    return response()->json([
                                        'status' => 0,
                                        'message' => 'Failed to retrieve scheduled posts'
                                    ], 500);
                                }
                            }

                            /**
                             * Publish scheduled post immediately
                             */
                            public function publishScheduledPost(Post $post): JsonResponse
                            {
                                try {
                                    if ($post->user_id !== auth()->id()) {
                                        return response()->json([
                                            'status' => 0,
                                            'message' => 'Unauthorized'
                                        ], 403);
                                    }

                                    if (!$post->is_scheduled) {
                                        return response()->json([
                                            'status' => 0,
                                            'message' => 'Post is not scheduled'
                                        ], 400);
                                    }

                                    $post->update([
                                        'is_published' => true,
                                        'published_at' => now(),
                                        'scheduled_at' => null,
                                    ]);

                                    return response()->json([
                                        'status' => 1,
                                        'message' => 'Post published successfully',
                                        'data' => $post
                                    ], $this->successStatus);

                                } catch (\Exception $e) {
                                    Log::error('Post publish failed', ['post_id' => $post->id, 'error' => $e->getMessage()]);
                                    return response()->json([
                                        'status' => 0,
                                        'message' => 'Failed to publish post'
                                    ], 500);
                                }
                            }

                            /**
                             * Get post analytics (for post owner)
                             */
                            public function getPostAnalytics(Post $post): JsonResponse
                            {
                                try {
                                    if ($post->user_id !== auth()->id()) {
                                        return response()->json([
                                            'status' => 0,
                                            'message' => 'Unauthorized'
                                        ], 403);
                                    }

                                    $analytics = [
                                        'views' => [
                                            'total' => $post->views_count,
                                            'unique' => $post->views()->distinct('user_id')->count('user_id'),
                                            'today' => $post->views()->whereDate('viewed_at', today())->count(),
                                            'this_week' => $post->views()->whereBetween('viewed_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
                                        ],
                                        'engagement' => [
                                            'likes_total' => $post->likes_count,
                                            'comments_total' => $post->comments_count,
                                            'shares_total' => $post->shares_count,
                                            'reaction_breakdown' => $post->getReactionCounts(),
                                        ],
                                        'reach' => [
                                            'views_by_day' => $post->views()
                                                ->selectRaw('DATE(viewed_at) as date, COUNT(*) as views')
                                                ->groupBy('date')
                                                ->orderBy('date')
                                                ->take(30)
                                                ->get(),
                                        ]
                                    ];

                                    return response()->json([
                                        'status' => 1,
                                        'message' => 'Analytics retrieved successfully',
                                        'data' => $analytics
                                    ], $this->successStatus);

                                } catch (\Exception $e) {
                                    Log::error('Analytics retrieval failed', ['post_id' => $post->id, 'error' => $e->getMessage()]);
                                    return response()->json([
                                        'status' => 0,
                                        'message' => 'Failed to retrieve analytics'
                                    ], 500);
                                }
                            }

                            /**
                             * Record post view for analytics
                             */
                            protected function recordView(Post $post, $user): void
                            {
                                try {
                                    // Don't record view for post owner
                                    if ($post->user_id === $user->id) {
                                        return;
                                    }

                                    // Check if user already viewed today (to prevent spam)
                                    $existingView = PostView::where('post_id', $post->id)
                                                          ->where('user_id', $user->id)
                                                          ->whereDate('viewed_at', today())
                                                          ->first();

                                    if (!$existingView) {
                                        PostView::create([
                                            'post_id' => $post->id,
                                            'user_id' => $user->id,
                                            'ip_address' => request()->ip(),
                                            'user_agent' => request()->userAgent(),
                                            'viewed_at' => now(),
                                        ]);
                                    }
                                } catch (\Exception $e) {
                                    // Don't fail the request if view recording fails
                                    Log::warning('View recording failed', ['post_id' => $post->id, 'error' => $e->getMessage()]);
                                }
                            }
                        }
