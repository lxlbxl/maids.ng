<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Employer Preference Resource
 * 
 * Transforms EmployerPreference model for API consumption.
 * Used for AI matching algorithm.
 * 
 * @package App\Http\Resources
 * @version 1.0.0
 */
class EmployerPreferenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employer_id' => $this->employer_id,

            // Requirements
            'work_type' => $this->work_type,
            'schedule_type' => $this->schedule_type,
            'location' => $this->location,
            'city' => $this->city,
            'state' => $this->state,

            // Preferences
            'skills_required' => $this->skills_required ?? [],
            'languages_required' => $this->languages_required ?? [],
            'experience_years' => $this->experience_years,
            'min_rating' => $this->min_rating,

            // Financial
            'max_budget' => $this->max_budget,
            'currency' => 'NGN',

            // Status
            'is_active' => (bool) $this->is_active,
            'is_filled' => (bool) $this->is_filled,

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
