<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * User Resource
 * 
 * Transforms User model for API consumption.
 * Designed for Agentic AI with clear field descriptions.
 * 
 * @package App\Http\Resources
 * @version 1.0.0
 */
class UserResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,

            // Role & Status
            'role' => $this->getRoleNames()->first(),
            'status' => $this->status,
            'is_verified' => $this->email_verified_at !== null,

            // Profile
            'avatar' => $this->avatar,
            'location' => $this->location,

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Role-specific data (loaded conditionally)
            'profile' => $this->when($this->hasRole('maid') && $this->maidProfile, function () {
                return new MaidProfileResource($this->maidProfile);
            }),

            'preferences' => $this->when($this->hasRole('employer') && $this->employerPreferences, function () {
                return EmployerPreferenceResource::collection($this->employerPreferences);
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
                'resource_type' => 'user',
                'version' => '1.0.0',
            ],
        ];
    }
}
