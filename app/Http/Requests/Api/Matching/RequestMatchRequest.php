<?php

namespace App\Http\Requests\Api\Matching;

use Illuminate\Foundation\Http\FormRequest;

class RequestMatchRequest extends FormRequest
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
            'job_type' => 'required|string|in:full_time,part_time,live_in,live_out',
            'location' => 'required|string|max:255',
            'salary_min' => 'required|numeric|min:10000',
            'salary_max' => 'required|numeric|gte:salary_min',
            'salary_day' => 'nullable|integer|min:1|max:31',
            'skills' => 'nullable|array',
            'skills.*' => 'string',
            'experience_years' => 'nullable|integer|min:0',
            'age_preference' => 'nullable|string',
            'language' => 'nullable|string',
            'religion' => 'nullable|string',
            'additional_requirements' => 'nullable|string|max:2000',
            'priority' => 'nullable|integer|min:1|max:10',
        ];
    }
}
