<?php

namespace App\Http\Requests\Api\Employer;

use Illuminate\Foundation\Http\FormRequest;

class CreateReviewRequest extends FormRequest
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
            'booking_id' => 'required|integer|exists:bookings,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
            'categories' => 'nullable|array',
            'categories.punctuality' => 'nullable|integer|min:1|max:5',
            'categories.cleanliness' => 'nullable|integer|min:1|max:5',
            'categories.communication' => 'nullable|integer|min:1|max:5',
            'categories.professionalism' => 'nullable|integer|min:1|max:5',
        ];
    }
}
