<?php

namespace App\Http\Requests\Api\Maid;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
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
            'bio' => 'nullable|string|max:1000',
            'skills' => 'nullable|array',
            'skills.*' => 'string',
            'experience_years' => 'nullable|integer|min:0|max:50',
            'help_types' => 'nullable|array',
            'help_types.*' => 'string|in:live-in,nanny,cooking,elderly-care,driver,cleaning,laundry,childcare',
            'schedule_preference' => 'nullable|string|in:live-in,part-time,full-time,weekends-only,flexible',
            'expected_salary' => 'nullable|integer|min:0',
            'location' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:100',
            'lga' => 'nullable|string|max:100',
            'bank_name' => 'nullable|string|max:100',
            'account_number' => 'nullable|string|max:20',
            'account_name' => 'nullable|string|max:100',
        ];
    }
}
