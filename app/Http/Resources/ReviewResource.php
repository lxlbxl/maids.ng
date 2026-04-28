<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Review Resource
 * 
 * Transforms Review model for API consumption.
 * 
 * @package App\Http\Resources
 * @version 1.0.0
 */
class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'reviewer_id' => $this->reviewer_id,
            'reviewee_id' => $this->reviewee_id,
            'reviewer_type' => $this->reviewer_type,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'is_verified' => (bool) $this->is_verified,
            'created_at' => $this->created_at?->toIso8601String(),

            'reviewer' => $this->whenLoaded('reviewer', function () {
                return [
                    'id' => $this->reviewer->id,
                    'name' => $this->reviewer->name,
                    'role' => $this->reviewer->getRoleNames()->first(),
                ];
            }),
        ];
    }
}
