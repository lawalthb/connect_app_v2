<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

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
            'email_verified_at' => $this->email_verified_at,
            'is_verified' => (bool)$this->is_verified,
            'bio' => $this->bio,
            'profile' => $this->profile,
            'profile_url' => $this->when($this->profile, function () {
                $baseUrl = config('app.url');
                $path = $this->profile_url ?? 'storage/profile';
                return $baseUrl . '/' . $path . '/' . $this->profile;
            }),
            'country_id' => $this->country_id,
            'country' => $this->when($this->relationLoaded('country'), function () {
                return [
                    'id' => $this->country->id,
                    'name' => $this->country->name,
                    'code' => $this->country->code
                ];
            }),
            'city' => $this->city,
            'state' => $this->state,
            'birth_date' => $this->birth_date,
            'gender' => $this->gender,
            'timezone' => $this->timezone,
            'interests' => $this->interests,
            'social_links' => $this->social_links,
            'is_online' => (bool)$this->is_online,
            'last_activity_at' => $this->last_activity_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'profile_completion' => $this->profile_completion,
            'social_circles' => $this->when($this->relationLoaded('socialCircles'), function () {
                return $this->socialCircles->map(function ($circle) {
                    return [
                        'id' => $circle->id,
                        'name' => $circle->name,
                        'icon' => $circle->icon
                    ];
                });
            }),
        ];
    }
}
