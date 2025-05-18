<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'username' => 'nullable|string|max:255|unique:users,username',
            'bio' => 'nullable|string|max:500',
            'country_id' => 'nullable|exists:countries,id',
            'phone' => 'nullable|string|max:20',
            'birth_date' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female,non_binary,other,prefer_not_to_say',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'timezone' => 'nullable|string|timezone',
            'interests' => 'nullable|array',
            'social_links' => 'nullable|array',
            'device_token' => 'nullable|string',
            'social_circles' => 'nullable|array',
            'social_circles.*' => 'exists:social_circles,id',
          //  'recaptcha_token' => 'required|string',
            'website' => 'prohibited', // Honeypot field - should always be empty
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return void
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation Error',
            'errors' => $validator->errors()
        ], 422));
    }
}
