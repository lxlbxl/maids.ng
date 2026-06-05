<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasRole('admin');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'matching_fee' => 'nullable|numeric|min:0',
            'platform_fee_percentage' => 'nullable|numeric|min:0|max:100',
            'min_withdrawal_amount' => 'nullable|numeric|min:0',
            'max_withdrawal_amount' => 'nullable|numeric|min:0',
            'work_hours_start' => 'nullable|integer|min:0|max:23',
            'work_hours_end' => 'nullable|integer|min:0|max:23',
        ];
    }
}
