<?php

namespace App\Http\Requests\Api\Assignment;

use Illuminate\Foundation\Http\FormRequest;

class RejectAssignmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->role === 'employer';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => 'required|string|max:1000',
            'request_replacement' => 'nullable|boolean',
        ];
    }
}
