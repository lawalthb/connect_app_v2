<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class GetUsersByCircleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'social_id' => 'required|exists:social_circles,id',
            'country_id' => 'nullable|exists:countries,id',
            'last_id' => 'nullable|integer',
            'limit' => 'nullable|integer|min:1|max:50',
        ];
    }
}
