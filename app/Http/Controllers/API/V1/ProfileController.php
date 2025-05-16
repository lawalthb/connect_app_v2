<?php
namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\API\BaseController;
use App\Http\Resources\V1\UserResource;
use App\Models\ProfileMultiUpload;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProfileController extends BaseController
{
    /**
     * Update the authenticated user's profile.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string',
            'username' => "nullable|string|unique:users,username,{$user->id},id,deleted_flag,N",
            'bio' => 'nullable|string',
            'country_id' => 'nullable|exists:countries,id',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error', $validator->errors(), 422);
        }

        $updateData = [];

        if ($request->has('name')) {
            $updateData['name'] = $request->name;
        }

        if ($request->has('username')) {
            $updateData['username'] = $request->username;
        }

        if ($request->has('bio')) {
            $updateData['bio'] = $request->bio;
        }

        if ($request->has('country_id')) {
            $updateData['country_id'] = $request->country_id;
        }

        // Handle profile pictures
        if ($request->has('profile') && is_array($request->profile) && count($request->profile) > 0) {
            // Soft delete existing profile pictures
            ProfileMultiUpload::where('user_id', $user->id)->update(['deleted_flag' => 'Y']);

            // Add new profile pictures
            foreach ($request->profile as $file) {
                $profileData = [
                    'profile' => $file,
                    'profile_url' => 'uploads/profile/',
                    'user_id' => $user->id,
                ];

                ProfileMultiUpload::create($profileData);
            }

            // Update the user's main profile picture with the first one
            $updateData['profile'] = $request->profile[0];
            $updateData['profile_url'] = 'uploads/profile/';
        }

        // Update social links if provided
        if ($request->has('social_links') && is_array($request->social_links)) {
            $existingLinks = json_decode($user->social_links, true) ?: [];
            $linkMap = [];

            foreach ($request->social_links as $link) {
                if (isset($link['platform']) && isset($link['url'])) {
                    $platform = strtolower($link['platform']);
                    $linkMap[$platform] = $link['url'];
                }
            }

            // Update existing links or add new ones
            foreach ($linkMap as $platform => $url) {
                $found = false;

                foreach ($existingLinks as &$existingLink) {
                    if (strtolower($existingLink['platform']) === $platform) {
                        $existingLink['url'] = $url;
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    $existingLinks[] = [
                        'platform' => $platform,
                        'url' => $url,
                    ];
                }
            }

            $updateData['social_links'] = json_encode($existingLinks);
        }

        $user->update($updateData);

        return $this->sendResponse('Profile updated successfully', [
            'user' => new UserResource($user->fresh()),
        ]);
    }

    /**
     * Upload a profile picture.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadProfilePicture(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'profile' => 'required|file|image|max:5120', // 5MB max
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error', $validator->errors(), 422);
        }

        $user = $request->user();

        if ($request->hasFile('profile')) {
            $file = $request->file('profile');
            $path = 'uploads/profile/';
            $filename = time() . '_' . $file->getClientOriginalName();

            // Store the file
            $file->storeAs($path, $filename, 'public');

            return $this->sendResponse('Profile picture uploaded successfully', [
                'name' => $filename,
            ]);
        }

        return $this->sendError('No file selected', null, 422);
    }

    /**
     * Upload multiple profile pictures.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadMultipleProfilePictures(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'profile.*' => 'required|file|image|max:5120', // 5MB max
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error', $validator->errors(), 422);
        }

        $user = $request->user();

        if ($request->hasFile('profile')) {
            $files = $request->file('profile');
            $uploadedFiles = [];

            // Soft delete existing profile pictures
            ProfileMultiUpload::where('user_id', $user->id)->update(['deleted_flag' => 'Y']);

            foreach ($files as $index => $file) {
                $path = 'uploads/profile/';
                $filename = time() . '_' . $index . '_' . $file->getClientOriginalName();

                // Store the file
                $file->storeAs($path, $filename, 'public');

                // Create profile record
                $profileData = [
                    'profile' => $filename,
                    'profile_url' => $path,
                    'user_id' => $user->id,
                    'type' => 'image',
                ];

                ProfileMultiUpload::create($profileData);

                $uploadedFiles[] = $filename;

                // Update the user's main profile picture with the first one
                if ($index === 0) {
                    $user->update([
                        'profile' => $filename,
                        'profile_url' => $path,
                    ]);
                }
            }

            return $this->sendResponse('Profile pictures uploaded successfully', [
                'files' => $uploadedFiles,
            ]);
        }

        return $this->sendError('No files selected', null, 422);
    }

    /**
     * Delete a social media link.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteSocialLink(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'platform' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error', $validator->errors(), 422);
        }

        $user = $request->user();
        $platform = strtolower($request->platform);

        $existingLinks = json_decode($user->social_links, true) ?: [];

        // Filter out the platform to be deleted
        $updatedLinks = array_filter($existingLinks, function($link) use ($platform) {
            return strtolower($link['platform']) !== $platform;
        });

        // Reindex array
        $updatedLinks = array_values($updatedLinks);

        $user->update([
            'social_links' => json_encode($updatedLinks),
        ]);

        return $this->sendResponse('Social link deleted successfully', [
            'social_links' => $updatedLinks,
        ]);
    }

    /**
     * Get the authenticated user's social media links.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSocialLinks(Request $request)
    {
        $user = $request->user();
        $socialLinks = json_decode($user->social_links, true) ?: [];

        return $this->sendResponse('Social links retrieved successfully', [
            'social_links' => $socialLinks,
        ]);
    }

    /**
     * Delete the authenticated user's account.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteAccount(Request $request)
    {
        $user = $request->user();

        // Soft delete the user
        $user->update([
            'deleted_flag' => 'Y',
            'deleted_at' => now(),
        ]);

        // Revoke all tokens
        $user->tokens()->delete();

        return $this->sendResponse('Account deleted successfully');
    }
}
