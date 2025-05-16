<?php
// app/Http/Controllers/API/V1/CommentController.php
namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\V1\CreateCommentRequest;
use App\Http\Resources\V1\CommentResource;
use App\Models\Post;
use App\Models\PostComment;
use Illuminate\Http\Request;

class CommentController extends BaseController
{
    /**
     * Store a newly created comment.
     *
     * @param CreateCommentRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(CreateCommentRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        $comment = PostComment::create($data);

        return $this->sendResponse('Comment created successfully', [
            'comment' => new CommentResource($comment->load('user')),
        ], 201);
    }

    /**
     * Display the comments for a post.
     *
     * @param Post $post
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Post $post)
    {
        $comments = $post->comments()
            ->with('user', 'replies.user')
            ->whereNull('comment_id') // Only get top-level comments
            ->latest()
            ->paginate(10);

        return $this->sendResponse('Comments retrieved successfully', [
            'comments' => CommentResource::collection($comments),
            'pagination' => [
                'total' => $comments->total(),
                'count' => $comments->count(),
                'per_page' => $comments->perPage(),
                'current_page' => $comments->currentPage(),
                'total_pages' => $comments->lastPage(),
            ],
        ]);
    }

    /**
     * Update the specified comment.
     *
     * @param Request $request
     * @param PostComment $comment
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, PostComment $comment)
    {
        // Check if the authenticated user can update this comment
        if ($comment->user_id !== auth()->id()) {
            return $this->sendError('Unauthorized', null, 403);
        }

        $validator = Validator::make($request->all(), [
            'comment' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error', $validator->errors(), 422);
        }

        $comment->update(['comment' => $request->comment]);

        return $this->sendResponse('Comment updated successfully', [
            'comment' => new CommentResource($comment->load('user')),
        ]);
    }

    /**
     * Remove the specified comment.
     *
     * @param PostComment $comment
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(PostComment $comment)
    {
        // Check if the authenticated user can delete this comment
        if ($comment->user_id !== auth()->id()) {
            return $this->sendError('Unauthorized', null, 403);
        }

        $comment->delete();

        return $this->sendResponse('Comment deleted successfully');
    }
}
