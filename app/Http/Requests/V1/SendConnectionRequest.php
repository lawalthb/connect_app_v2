<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class SendConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => 'required|exists:users,id',
            'social_id' => 'nullable|exists:social_circles,id',
            'request_type' => 'required|in:right_swipe,left_swipe,super_like,direct_request',
            'message' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'User ID is required',
            'user_id.exists' => 'User does not exist',
            'request_type.in' => 'Invalid request type',
        ];
    }
}
