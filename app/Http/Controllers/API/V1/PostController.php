<?php
namespace App\Http\Controllers\API\V1;
use App\Http\Controllers\API\BaseController;
use App\Http\Requests\V1\CreatePostRequest;
use App\Http\Requests\V1\UpdatePostRequest;
use App\Http\Resources\V1\PostResource;
use App\Http\Resources\V1\PostCollection;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PostController extends BaseController
{
    /**
     * Display a listing of the posts.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $posts = Post::with('user')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(10);

        return $this->sendResponse('Posts retrieved successfully', [
            'posts' => new PostCollection($posts),
        ]);
    }

    /**
     * Store a newly created post.
     *
     * @param CreatePostRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(CreatePostRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        // Handle file upload if present
        if ($request->hasFile('file')) {
            $data['file_url'] = 'uploads/post';
            $data['file'] = $request->file('file')->store('posts', 'public');
        }

        $post = Post::create($data);

        // Handle tagged users if present
        if ($request->has('tagged_user_ids')) {
            $post->taggedUsers()->sync($request->tagged_user_ids);
        }

        return $this->sendResponse('Post created successfully', [
            'post' => new PostResource($post->load('user', 'taggedUsers')),
        ], 201);
    }

    /**
     * Display the specified post.
     *
     * @param Post $post
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Post $post)
    {
        // Check if the authenticated user can view this post
        if ($post->user_id !== auth()->id() && !$post->isPublic()) {
            return $this->sendError('Unauthorized', null, 403);
        }

        return $this->sendResponse('Post retrieved successfully', [
            'post' => new PostResource($post->load('user', 'taggedUsers', 'comments', 'likes')),
        ]);
    }

    /**
     * Update the specified post.
     *
     * @param UpdatePostRequest $request
     * @param Post $post
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdatePostRequest $request, Post $post)
    {
        // Check if the authenticated user can update this post
        if ($post->user_id !== auth()->id()) {
            return $this->sendError('Unauthorized', null, 403);
        }

        $data = $request->validated();

        // Handle file upload if present
        if ($request->hasFile('file')) {
            // Delete old file if exists
            if ($post->file && Storage::disk('public')->exists($post->file)) {
                Storage::disk('public')->delete($post->file);
            }

            $data['file_url'] = 'uploads/post';
            $data['file'] = $request->file('file')->store('posts', 'public');
        }

        $post->update($data);

        // Handle tagged users if present
        if ($request->has('tagged_user_ids')) {
            $post->taggedUsers()->sync($request->tagged_user_ids);
        }

        return $this->sendResponse('Post updated successfully', [
            'post' => new PostResource($post->load('user', 'taggedUsers')),
        ]);
    }

    /**
     * Remove the specified post.
     *
     * @param Post $post
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Post $post)
    {
        // Check if the authenticated user can delete this post
        if ($post->user_id !== auth()->id()) {
            return $this->sendError('Unauthorized', null, 403);
        }

        // Delete file if exists
        if ($post->file && Storage::disk('public')->exists($post->file)) {
            Storage::disk('public')->delete($post->file);
        }

        $post->delete();

        return $this->sendResponse('Post deleted successfully');
    }

    /**
     * Like or unlike a post.
     *
     * @param Post $post
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleLike(Post $post)
    {
        $user = auth()->user();

        if ($post->likes()->where('user_id', $user->id)->exists()) {
            $post->likes()->where('user_id', $user->id)->delete();
            $action = 'unliked';
        } else {
            $post->likes()->create(['user_id' => $user->id]);
            $action = 'liked';
        }

        return $this->sendResponse("Post {$action} successfully", [
            'post' => new PostResource($post->load('user', 'likes')),
        ]);
    }

    /**
     * Get posts for the feed.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function feed(Request $request)
    {
        $user = $request->user();

        // Get IDs of users the authenticated user is connected with
        $connectedUserIds = $user->connections()->pluck('id')->toArray();
        $connectedUserIds[] = $user->id; // Include user's own posts

        $posts = Post::with('user', 'taggedUsers')
            ->whereIn('user_id', $connectedUserIds)
            ->latest()
            ->paginate(10);

        return $this->sendResponse('Feed retrieved successfully', [
            'posts' => new PostCollection($posts),
        ]);
    }
}
