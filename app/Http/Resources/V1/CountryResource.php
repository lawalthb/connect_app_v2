<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class CountryResource extends JsonResource
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
            'code' => $this->code,
            'phone_code' => $this->phone_code,
            'timezone' => $this->timezone,
            'flag' => $this->flag,
            'emoji' => $this->emoji,
            'currency' => $this->currency,
            'currency_code' => $this->currency_code,
            'currency_symbol' => $this->currency_symbol,
        ];
    }
}
