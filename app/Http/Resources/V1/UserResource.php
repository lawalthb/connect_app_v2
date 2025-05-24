<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use App\Helpers\TimezoneHelper;

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
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'username' => $this->username,
            'email_verified_at' => $this->email_verified_at ?
                TimezoneHelper::convertToUserTimezone($this->email_verified_at, $this->timezone) : null,
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
                    'timezone' => $this->country->timezone
                ];
            }),
            'city' => $this->city,
            'state' => $this->state,
            'birth_date' => $this->birth_date,
            'gender' => $this->gender,
            'timezone' => $this->timezone, // Include user's timezone
            'interests' => $this->interests,
            'social_links' => $this->social_links,
            'is_online' => (bool)$this->is_online,
            'last_activity_at' => $this->last_activity_at ?
                TimezoneHelper::convertToUserTimezone($this->last_activity_at, $this->timezone) : null,
            'created_at' => TimezoneHelper::convertToUserTimezone($this->created_at, $this->timezone),
            'updated_at' => TimezoneHelper::convertToUserTimezone($this->updated_at, $this->timezone),
            'profile_completion' => $this->profile_completion,

            // Load social circles without triggering the ambiguous query
            'social_circles' => $this->when($this->relationLoaded('socialCircles'), function () {
                return $this->socialCircles->map(function ($circle) {
                    return [
                        'id' => $circle->id,
                        'name' => $circle->name,
                        'logo' => $circle->logo,
                        'logo_url' => $circle->logo_url
                    ];
                });
            }),

            // Profile uploads
            'profile_uploads' => $this->when($this->relationLoaded('profileUploads'), function () {
                return $this->profileUploads->map(function ($upload) {
                    return [
                        'id' => $upload->id,
                        'file_name' => $upload->file_name,
                        'file_url' => $upload->file_url . $upload->file_name,
                        'file_type' => $upload->file_type,
                        'created_at' => TimezoneHelper::convertToUserTimezone($upload->created_at, $this->timezone),
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
        // If no profile image, return null
        if (!$this->profile) {
            return null;
        }

        // If profile_url already contains a full URL, return it
        if ($this->profile_url && filter_var($this->profile_url, FILTER_VALIDATE_URL)) {
            return $this->profile_url;
        }

        // If we have an S3 path stored in profile_url
        if ($this->profile_url && str_starts_with($this->profile_url, 's3://')) {
            return Storage::disk('s3')->url(str_replace('s3://', '', $this->profile_url));
        }

        // Handle different storage formats - check if profile_url contains a path
        if ($this->profile_url) {
            // If it's a path (not a full URL), construct the S3 URL
            $path = trim($this->profile_url, '/') . '/' . $this->profile;
            return Storage::disk('s3')->url($path);
        }

        // Default case - just the filename
        return Storage::disk('s3')->url('profiles/' . $this->profile);
    }
}
