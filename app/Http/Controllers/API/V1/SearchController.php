<?php
// app/Http/Controllers/API/V1/SearchController.php
namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Resources\V1\PostResource;
use App\Http\Resources\V1\UserResource;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;

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
        $connectedUserIds = $user->connections()->pluck('id')->toArray();
        $connectedUserIds[] = $user->id; // Include user's own posts

        $posts = Post::where('message', 'like', "%{$query}%")
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
     * Search for users by social circle.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchUsersBySocialCircle(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'social_id' => 'required|exists:social_circles,id',
            'country_id' => 'nullable|exists:countries,id',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error', $validator->errors(), 422);
        }

        $socialId = $request->social_id;
        $countryId = $request->country_id;
        $lastId = $request->last_id ?? 0;

        $query = User::join('user_social_circles', 'users.id', '=', 'user_social_circles.user_id')
            ->where('user_social_circles.social_id', $socialId)
            ->where('user_social_circles.deleted_flag', 'N')
            ->where('users.id', '>', $lastId)
            ->where('users.id', '!=', auth()->id());

        if ($countryId) {
            $query->where('users.country_id', $countryId);
        }

        $users = $query->select('users.*')
            ->orderBy('users.id')
            ->limit(20)
            ->get();

        return $this->sendResponse('Users retrieved successfully', [
            'users' => UserResource::collection($users),
            'last_id' => $users->count() > 0 ? $users->last()->id : $lastId,
        ]);
    }
}
