<?php
namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Resources\V1\SocialCircleResource;
use App\Models\SocialCircle;
use App\Models\UserSocialCircle;
use Illuminate\Http\Request;

class SocialCircleController extends BaseController
{
    /**
     * Display a listing of the social circles.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $socialCircles = SocialCircle::all();

        return $this->sendResponse('Social circles retrieved successfully', [
            'social_circles' => SocialCircleResource::collection($socialCircles),
        ]);
    }

    /**
     * Display the social circles for the authenticated user.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function userSocialCircles(Request $request)
    {
        $user = $request->user();
        $socialCircles = $user->socialCircles;

        return $this->sendResponse('User social circles retrieved successfully', [
            'social_circles' => SocialCircleResource::collection($socialCircles),
        ]);
    }

    /**
     * Update the social circles for the authenticated user.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateUserSocialCircles(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'social_circle_ids' => 'required|array',
            'social_circle_ids.*' => 'exists:social_circles,id',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error', $validator->errors(), 422);
        }

        $user = $request->user();

        // Soft delete existing social circles
        UserSocialCircle::where('user_id', $user->id)->update(['deleted_flag' => 'Y']);

        // Add new social circles
        foreach ($request->social_circle_ids as $socialCircleId) {
            UserSocialCircle::updateOrCreate(
                ['user_id' => $user->id, 'social_id' => $socialCircleId],
                ['deleted_flag' => 'N']
            );
        }

        return $this->sendResponse('User social circles updated successfully', [
            'social_circles' => SocialCircleResource::collection($user->fresh()->socialCircles),
        ]);
    }
}
