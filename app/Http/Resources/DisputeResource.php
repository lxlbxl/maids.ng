<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Dispute Resource
 * 
 * Transforms Dispute model for API consumption.
 * 
 * @package App\Http\Resources
 * @version 1.0.0
 */
class DisputeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'raised_by' => $this->raised_by,
            'type' => $this->type,
            'reason' => $this->reason,
            'status' => $this->status,
            'resolution' => $this->resolution,
            'ai_assessed' => (bool) $this->ai_assessed,
            'ai_decision' => $this->ai_decision,
            'refund_amount' => $this->refund_amount,
            'created_at' => $this->created_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),

            'raised_by_user' => $this->whenLoaded('raisedByUser', function () {
                return [
                    'id' => $this->raisedByUser->id,
                    'name' => $this->raisedByUser->name,
                ];
            }),
        ];
    }
}
