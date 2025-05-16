<?php
namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePostRequest extends FormRequest
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
            'message' => 'nullable|string',
            'file' => 'nullable|file|max:10240', // 10MB max
            'type' => 'sometimes|string|in:text,image,video',
            'social_id' => 'sometimes|integer|exists:social_circles,id',
            'tagged_user_ids' => 'nullable|array',
            'tagged_user_ids.*' => 'exists:users,id',
        ];
    }
}
