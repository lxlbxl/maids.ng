<?php

namespace App\Http\Requests\Api\Maid;

use Illuminate\Foundation\Http\FormRequest;

class ListAssignmentsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->isMaid();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => 'nullable|string|in:pending_acceptance,accepted,rejected,completed,cancelled,all',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}
