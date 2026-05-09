<?php

namespace App\Http\Requests\Api\Employer;

use Illuminate\Foundation\Http\FormRequest;

class CreatePreferenceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasRole('employer');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'help_type' => 'required|string|in:live-in,nanny,cooking,elderly-care,driver,cleaning,laundry',
            'location' => 'required|string|max:255',
            'state' => 'required|string|max:100',
            'lga' => 'nullable|string|max:100',
            'schedule_type' => 'required|string|in:full-time,part-time,weekends,flexible',
            'salary_budget' => 'required|integer|min:10000|max:500000',
            'required_skills' => 'nullable|array',
            'required_skills.*' => 'string|max:50',
            'num_children' => 'nullable|integer|min:0|max:10',
            'children_ages' => 'nullable|array',
            'children_ages.*' => 'integer|min:0|max:18',
            'has_elderly' => 'nullable|boolean',
            'elderly_condition' => 'nullable|string|max:500',
            'special_requirements' => 'nullable|string|max:1000',
            'start_date' => 'nullable|date|after_or_equal:today',
            'urgency' => 'nullable|string|in:immediate,within_week,within_month,flexible',
        ];
    }
}
