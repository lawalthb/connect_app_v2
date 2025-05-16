<?php
namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class CreateCommentRequest extends FormRequest
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
            'comment' => 'required|string',
            'post_id' => 'required|integer|exists:posts,id',
            'type' => 'required|string|in:Comment,Reply',
            'comment_id' => 'required_if:type,Reply|nullable|integer|exists:post_comments,id',
        ];
    }
}
