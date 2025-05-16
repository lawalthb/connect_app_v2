<?php
namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class CreateStoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => 'required|string|in:text,image,video',
            'story' => 'required_if:type,image,video|file|max:10240', // 10MB max
            'message' => 'nullable|string',
            'tagged_user_ids' => 'nullable|array',
            'tagged_user_ids.*' => 'exists:users,id',
        ];
    }
}
