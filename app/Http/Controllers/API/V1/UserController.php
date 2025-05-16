<?php
namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\V1\UpdateProfileImageRequest;
use App\Http\Requests\V1\UpdateSocialLinksRequest;
use App\Http\Requests\V1\UpdateUserRequest;
use App\Http\Resources\V1\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UserController extends BaseController
{
    /**
     * Display the authenticated user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request)
    {
        return $this->sendResponse('User retrieved successfully', [
            'user' => new UserResource($request->user()),
        ]);
    }

    /**
     * Update the authenticated user
     *
     * @param UpdateUserRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateUserRequest $request)
    {
        $user = $request->user();
        $user->update($request->validated());

        return $this->sendResponse('User updated successfully', [
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Update user profile image
     *
     * @param UpdateProfileImageRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateProfileImage(UpdateProfileImageRequest $request)
    {
        $user = $request->user();

        // Delete old profile image if exists
        if ($user->profile_image && Storage::disk('public')->exists($user->profile_image)) {
            Storage::disk('public')->delete($user->profile_image);
        }

        // Store new profile image
        $path = $request->file('image')->store('profile-images', 'public');
        $user->update(['profile_image' => $path]);

        return $this->sendResponse('Profile image updated successfully', [
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Update user social links
     *
     * @param UpdateSocialLinksRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateSocialLinks(UpdateSocialLinksRequest $request)
    {
        $user = $request->user();
        $user->update(['social_links' => $request->social_links]);

        return $this->sendResponse('Social links updated successfully', [
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Delete user account
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        $user = $request->user();

        // Revoke all tokens
        $user->tokens()->delete();

        // Soft delete the user
        $user->delete();

        return $this->sendResponse('User account deleted successfully');
    }
}
