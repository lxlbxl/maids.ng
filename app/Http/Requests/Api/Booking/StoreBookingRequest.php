<?php

namespace App\Http\Requests\Api\Booking;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
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
            'preference_id' => 'required|integer|exists:employer_preferences,id',
            'maid_id' => 'required|integer|exists:users,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'schedule_type' => 'required|string|in:full-time,part-time,weekends,flexible',
            'agreed_salary' => 'required|integer|min:10000|max:500000',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
