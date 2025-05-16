<?php
// app/Http/Requests/V2/RegisterRequest.php
namespace App\Http\Requests\V2;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Password::defaults()],
            'username' => 'nullable|string|max:255|unique:users', // V2 enhancement
            'bio' => 'nullable|string', // V2 enhancement
            'country_id' => 'nullable|exists:countries,id', // V2 enhancement
            'phone' => 'nullable|string', // V2 enhancement
        ];
    }
}
