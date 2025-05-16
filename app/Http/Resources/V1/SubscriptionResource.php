<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'duration' => $this->duration,
            'status' => $this->when(isset($this->status), $this->status),
            'payment_status' => $this->when(isset($this->payment_status), $this->payment_status),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
