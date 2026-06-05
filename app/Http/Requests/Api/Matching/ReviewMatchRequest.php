<?php

namespace App\Http\Requests\Api\Matching;

use Illuminate\Foundation\Http\FormRequest;

class ReviewMatchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->role === 'admin';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decision' => 'required|string|in:approve,reject,retry',
            'notes' => 'nullable|string|max:2000',
        ];
    }
}
