<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class CreateStoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => 'required|in:text,image,video',
            'content' => 'required_if:type,text|nullable|string|max:1000',
            'file' => 'required_if:type,image,video|nullable|file|max:50000', // 50MB max
            'caption' => 'nullable|string|max:500',
            'background_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'font_settings' => 'nullable|array',
            'font_settings.size' => 'nullable|integer|min:12|max:72',
            'font_settings.family' => 'nullable|string|in:arial,helvetica,georgia,times',
            'font_settings.weight' => 'nullable|string|in:normal,bold',
            'privacy' => 'nullable|in:all_connections,close_friends,custom',
            'custom_viewers' => 'nullable|array',
            'custom_viewers.*' => 'exists:users,id',
            'allow_replies' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'content.required_if' => 'Content is required for text stories.',
            'file.required_if' => 'File is required for image and video stories.',
            'file.max' => 'File size cannot exceed 50MB.',
            'background_color.regex' => 'Background color must be a valid hex color (e.g., #FF0000).',
        ];
    }
}
