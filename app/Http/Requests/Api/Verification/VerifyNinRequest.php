<?php

namespace App\Http\Requests\Api\Verification;

use Illuminate\Foundation\Http\FormRequest;

class VerifyNinRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Verification can be public or auth-dependent
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nin' => 'required|string|size:11|regex:/^[0-9]{11}$/',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'dob' => 'nullable|date|before:today',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'gender' => 'nullable|in:male,female,m,f',
        ];
    }
}
