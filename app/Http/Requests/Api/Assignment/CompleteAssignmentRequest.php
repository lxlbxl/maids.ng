<?php

namespace App\Http\Requests\Api\Assignment;

use Illuminate\Foundation\Http\FormRequest;

class CompleteAssignmentRequest extends FormRequest
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
            'completion_notes' => 'nullable|string|max:1000',
            'rating' => 'nullable|integer|min:1|max:5',
            'feedback' => 'nullable|string|max:2000',
        ];
    }
}
