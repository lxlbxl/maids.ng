<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Maid Profile Resource
 * 
 * Transforms MaidProfile model for API consumption.
 * Optimized for AI agent matching and search operations.
 * 
 * @package App\Http\Resources
 * @version 1.0.0
 */
class MaidProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * 
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // Identity
            'id' => $this->id,
            'user_id' => $this->user_id,

            // Profile Info
            'bio' => $this->bio,
            'role' => $this->getMaidRole(),
            'skills' => $this->skills ?? [],
            'languages' => $this->languages ?? [],

            // Work Preferences
            'work_type' => $this->work_type,
            'schedule_preference' => $this->schedule_preference,
            'availability_status' => $this->availability_status,

            // Location
            'location' => $this->location,
            'city' => $this->city,
            'state' => $this->state,

            // Financial
            'expected_salary' => $this->expected_salary,
            'salary_currency' => 'NGN',

            // Experience
            'experience_years' => $this->experience_years,
            'previous_employers_count' => $this->previous_employers_count,

            // Ratings
            'rating' => round($this->rating, 2),
            'total_reviews' => $this->total_reviews,
            'total_bookings' => $this->total_bookings,

            // Verification Status
            'verification' => [
                'nin_verified' => (bool) $this->nin_verified,
                'background_verified' => (bool) $this->background_verified,
                'fully_verified' => (bool) ($this->nin_verified && $this->background_verified),
            ],

            // Documents
            'nin_number' => $this->when($request->user()?->hasRole('admin'), $this->nin_number),
            'documents' => $this->when($request->user()?->hasRole('admin'), $this->documents),

            // AI Matching Score (if available)
            'match_score' => $this->when(isset($this->match_score), $this->match_score),
            'match_confidence' => $this->when(isset($this->match_confidence), $this->match_confidence),

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Related Data (when loaded)
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'avatar' => $this->user->avatar,
                ];
            }),

            'reviews' => $this->whenLoaded('reviews', function () {
                return ReviewResource::collection($this->reviews);
            }),
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     * 
     * @param Request $request
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'resource_type' => 'maid_profile',
                'version' => '1.0.0',
            ],
        ];
    }
}
