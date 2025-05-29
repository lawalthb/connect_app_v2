<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class ReplyToStoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => 'required|in:text,emoji,media',
            'content' => 'required_if:type,text,emoji|nullable|string|max:1000',
            'file' => 'required_if:type,media|nullable|file|max:10000', // 10MB for replies
        ];
    }

    public function messages(): array
    {
        return [
            'content.required_if' => 'Content is required for text and emoji replies.',
            'file.required_if' => 'File is required for media replies.',
        ];
    }
}
