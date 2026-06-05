<?php

namespace App\Http\Requests\Api\Verification;

use Illuminate\Foundation\Http\FormRequest;

class BatchVerifyNinRequest extends FormRequest
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
            'verifications' => 'required|array|max:10',
            'verifications.*.nin' => 'required|string|size:11|regex:/^[0-9]{11}$/',
            'verifications.*.first_name' => 'required|string|max:255',
            'verifications.*.last_name' => 'required|string|max:255',
            'verifications.*.middle_name' => 'nullable|string|max:255',
            'verifications.*.dob' => 'nullable|date|before:today',
            'verifications.*.phone' => 'nullable|string|max:20',
            'verifications.*.email' => 'nullable|email|max:255',
            'verifications.*.gender' => 'nullable|in:male,female,m,f',
        ];
    }
}
