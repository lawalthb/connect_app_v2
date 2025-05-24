<?php

namespace App\Http\Controllers\API\V1;

use App\Helpers\TimezoneHelper;
use App\Http\Controllers\API\BaseController;
use App\Http\Requests\V1\UpdateProfileImageRequest;
use App\Http\Requests\V1\UpdateSocialLinksRequest;
use App\Http\Requests\V1\UpdateUserRequest;
use App\Http\Resources\V1\UserResource;
use App\Http\Resources\V1\CountryResource;
use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

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

    /**
 * Get all countries
 *
 * @return \Illuminate\Http\JsonResponse
 */
public function getCountries()
{
    try {
        $countries = Cache::remember('countries', 60*24, function () {
            return Country::where('active', true)
                ->orderBy('name', 'asc')
                ->get();
        });

        return $this->sendResponse('Countries retrieved successfully', [
            'countries' => CountryResource::collection($countries)
        ]);
    } catch (\Exception $e) {
        return $this->sendError('Failed to retrieve countries', $e->getMessage(), 500);
    }
}

    /**
     * Get list of available timezones
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTimezones()
    {
        //return 5;
        try {
            $timezones = TimezoneHelper::getTimezoneList();

            return $this->sendResponse('Timezones retrieved successfully', [
                'timezones' => $timezones
            ]);
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve timezones', $e->getMessage(), 500);
        }
    }

    /**
     * Update user timezone
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateTimezone(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'timezone' => 'required|string|timezone'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 422);
        }

        try {
            $user = $request->user();
            $user->timezone = $request->timezone;
            $user->save();

            return $this->sendResponse('Timezone updated successfully', [
                'user' => new UserResource($user)
            ]);
        } catch (\Exception $e) {
            return $this->sendError('Failed to update timezone', $e->getMessage(), 500);
        }
    }
}
