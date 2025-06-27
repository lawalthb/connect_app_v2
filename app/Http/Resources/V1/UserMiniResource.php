<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class UserMiniResource extends JsonResource
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
            'username' => $this->username,
            'profile' => $this->profile,
            'profile_url' => $this->profile_url,
            'full_profile_url' => $this->profile_url ?
                (str_starts_with($this->profile_url, 'http') ?
                    $this->profile_url . $this->profile :
                    config('app.url') . '/' . $this->profile_url . $this->profile
                ) : null,
        ];
    }
}
