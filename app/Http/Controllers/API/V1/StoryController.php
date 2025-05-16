<?php
namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\V1\CreateStoryRequest;
use App\Http\Resources\V1\StoryResource;
use App\Models\Story;
use App\Models\StoryView;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StoryController extends BaseController
{
    /**
     * Store a newly created story.
     *
     * @param CreateStoryRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(CreateStoryRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        // Handle file upload if present
        if ($request->hasFile('story')) {
            $data['url'] = 'uploads/story';
            $data['story'] = $request->file('story')->store('stories', 'public');
        }

        $story = Story::create($data);

        // Handle tagged users if present
        if ($request->has('tagged_user_ids')) {
            $story->taggedUsers()->sync($request->tagged_user_ids);
        }

        return $this->sendResponse('Story created successfully', [
            'story' => new StoryResource($story->load('user', 'taggedUsers')),
        ], 201);
    }

    /**
     * Display the stories for the authenticated user.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function userStories(Request $request)
    {
        $user = $request->user();
        $stories = $user->stories()->latest()->get();

        return $this->sendResponse('User stories retrieved successfully', [
            'stories' => StoryResource::collection($stories->load('user', 'taggedUsers')),
        ]);
    }

    /**
     * Display the stories for a specific user.
     *
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserStories($userId)
    {
        $stories = Story::where('user_id', $userId)
            ->where('created_at', '>=', now()->subDay())
            ->latest()
            ->get();

        return $this->sendResponse('User stories retrieved successfully', [
            'stories' => StoryResource::collection($stories->load('user', 'taggedUsers')),
        ]);
    }

    /**
     * Display the stories for the authenticated user's feed.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function feed(Request $request)
    {
        $user = $request->user();

        // Get IDs of users the authenticated user is connected with
        $connectedUserIds = $user->connections()->pluck('id')->toArray();

        // Get stories from connected users and the authenticated user
        $stories = Story::whereIn('user_id', array_merge($connectedUserIds, [$user->id]))
            ->where('created_at', '>=', now()->subDay())
            ->with('user', 'taggedUsers')
            ->latest()
            ->get()
            ->groupBy('user_id');

        $formattedStories = [];

        foreach ($stories as $userId => $userStories) {
            $formattedStories[] = [
                'user' => new UserResource($userStories->first()->user),
                'stories' => StoryResource::collection($userStories),
            ];
        }

        return $this->sendResponse('Stories feed retrieved successfully', [
            'user_stories' => $formattedStories,
        ]);
    }

    /**
     * Mark a story as viewed.
     *
     * @param Request $request
     * @param int $storyId
     * @return \Illuminate\Http\JsonResponse
     */
    public function view(Request $request, $storyId)
    {
        $user = $request->user();
        $story = Story::findOrFail($storyId);

        // Check if the user has already viewed this story
        $existingView = StoryView::where('user_id', $user->id)
            ->where('story_id', $storyId)
            ->first();

        if (!$existingView) {
            StoryView::create([
                'user_id' => $user->id,
                'story_id' => $storyId,
            ]);
        }

        return $this->sendResponse('Story marked as viewed');
    }

    /**
     * Delete a story.
     *
     * @param int $storyId
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($storyId)
    {
        $user = auth()->user();
        $story = Story::findOrFail($storyId);

        // Check if the authenticated user owns this story
        if ($story->user_id !== $user->id) {
            return $this->sendError('Unauthorized', null, 403);
        }

        // Delete file if exists
        if ($story->story && $story->url && Storage::disk('public')->exists($story->story)) {
            Storage::disk('public')->delete($story->story);
        }

        $story->delete();

        return $this->sendResponse('Story deleted successfully');
    }
}
