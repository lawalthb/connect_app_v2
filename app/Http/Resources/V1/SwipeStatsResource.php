<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class SwipeStatsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'date' => $this->swipe_date->toDateString(),
            'right_swipes' => $this->right_swipes,
            'left_swipes' => $this->left_swipes,
            'super_likes' => $this->super_likes,
            'total_swipes' => $this->total_swipes,
            'daily_limit' => $this->daily_limit,
            'remaining_swipes' => max(0, $this->daily_limit - $this->total_swipes),
            'can_swipe' => $this->canSwipe(),
        ];
    }
}
