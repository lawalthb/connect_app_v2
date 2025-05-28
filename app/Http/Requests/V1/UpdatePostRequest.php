<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Post;

class UpdatePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        $post = $this->route('post');

        return auth()->check() &&
               auth()->id() === $post->user_id &&
               $post->created_at->isToday(); // Can only edit same day
    }

    public function rules(): array
    {
        return [
            'content' => 'nullable|string|max:5000',
            'social_circle_id' => 'sometimes|exists:social_circles,id',
            'location' => 'nullable|array',
            'location.lat' => 'nullable|numeric|between:-90,90',
            'location.lng' => 'nullable|numeric|between:-180,180',
            'location.address' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'content.max' => 'Post content cannot exceed 5000 characters.',
            'social_circle_id.exists' => 'Selected social circle does not exist.',
        ];
    }
}
