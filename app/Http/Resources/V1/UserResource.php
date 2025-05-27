<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use App\Helpers\TimezoneHelper;
use App\Models\User; // Make sure User model is imported

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        // The $this->resource is the User model instance
        // Or, within this toArray method, $this often refers to the User model instance
        // due to JsonResource's magic methods. For clarity, $this->resource is explicit.
        /** @var User $userModelInstance */
        $userModelInstance = $this->resource;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'username' => $this->username,
            'email_verified_at' => $this->email_verified_at ?
                TimezoneHelper::convertToUserTimezone($this->email_verified_at, $userModelInstance)?->toISOString() : null,
            'is_verified' => (bool)$this->is_verified,
            'bio' => $this->bio,
            'profile' => $this->profile,
            'profile_url' => $this->whenNotNull($this->getProfileUrl()),
            'country_id' => $this->country_id,
            'country' => $this->when($this->relationLoaded('country'), function () {
                return [
                    'id' => $this->country->id,
                    'name' => $this->country->name,
                    'code' => $this->country->code,
                    'timezone' => $this->country->timezone // This is country's timezone, not user's
                ];
            }),
            'city' => $this->city,
            'state' => $this->state,
            'birth_date' => $this->birth_date,
            'gender' => $this->gender,
            'timezone' => $this->timezone, // User's own timezone string
            'interests' => $this->interests, // Assuming this is already an array or JSON
            'social_links' => $this->social_links, // Assuming this is already an array or JSON
            'is_online' => (bool)$this->is_online,
            'last_activity_at' => $this->last_activity_at ?
                TimezoneHelper::convertToUserTimezone($this->last_activity_at, $userModelInstance)?->toISOString() : null,
            'created_at' => TimezoneHelper::convertToUserTimezone($this->created_at, $userModelInstance)?->toISOString(),
            'updated_at' => TimezoneHelper::convertToUserTimezone($this->updated_at, $userModelInstance)?->toISOString(),
            'profile_completion' => $this->profile_completion,

            'social_circles' => $this->when($this->relationLoaded('socialCircles'), function () {
                return $this->socialCircles->map(function ($circle) {
                    return [
                        'id' => $circle->id,
                        'name' => $circle->name,
                        'logo' => $circle->logo,
                        'logo_url' => $circle->logo_url // Assuming logo_url is already correct
                    ];
                });
            }),

            'profile_uploads' => $this->when($this->relationLoaded('profileUploads'), function () use ($userModelInstance) {
                return $this->profileUploads->map(function ($upload) use ($userModelInstance) {
                    return [
                        'id' => $upload->id,
                        'file_name' => $upload->file_name,
                        // Ensure file_url is correctly constructed. If it's a full URL, no change.
                        // If it's a relative path for S3, Storage::url() might be needed if not already handled.
                        'file_url' => $upload->file_url . $upload->file_name, // Review this line based on how file_url is stored
                        'file_type' => $upload->file_type,
                        'created_at' => TimezoneHelper::convertToUserTimezone($upload->created_at, $userModelInstance)?->toISOString(),
                    ];
                });
            }),
        ];
    }

    /**
     * Get properly formatted profile URL
     *
     * @return string|null
     */
    protected function getProfileUrl()
    {
        if (!$this->profile) {
            return null;
        }

        // If profile_url already contains a full URL (e.g. from social login)
        if ($this->profile_url && filter_var($this->profile_url, FILTER_VALIDATE_URL)) {
            return $this->profile_url;
        }

        // If profile_url is an S3 path (e.g., 'profiles/image.jpg') and profile is just the filename
        // This logic might need adjustment based on how you store profile and profile_url
        if ($this->profile_url && $this->profile) {
             // Assuming profile_url might be a directory path and profile is the filename
            $path = trim($this->profile_url, '/') . '/' . $this->profile;
            return Storage::disk(env('FILESYSTEM_DISK', 'public'))->url($path);
        }

        // If only profile (filename) is available, assume it's in a default 'profiles' S3 directory
        if ($this->profile) {
            return Storage::disk(env('FILESYSTEM_DISK', 'public'))->url('profiles/' . $this->profile);
        }

        return null;
    }
}
