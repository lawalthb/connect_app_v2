<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class RespondToConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'request_id' => 'required|exists:user_requests,id',
            'action' => 'required|in:accept,reject,block',
            'message' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'request_id.required' => 'Request ID is required',
            'request_id.exists' => 'Connection request does not exist',
            'action.in' => 'Action must be accept, reject, or block',
        ];
    }
}
