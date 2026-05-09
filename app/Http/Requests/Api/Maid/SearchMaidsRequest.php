<?php

namespace App\Http\Requests\Api\Maid;

use Illuminate\Foundation\Http\FormRequest;

class SearchMaidsRequest extends FormRequest
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
            'location' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:100',
            'lga' => 'nullable|string|max:100',
            'help_types' => 'nullable|array',
            'help_types.*' => 'string|in:live-in,nanny,cooking,elderly-care,driver,cleaning,laundry,childcare',
            'min_experience' => 'nullable|integer|min:0',
            'max_salary' => 'nullable|integer|min:0',
            'schedule_preference' => 'nullable|string|in:live-in,part-time,full-time,weekends-only,flexible',
            'verified_only' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}
