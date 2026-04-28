<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Booking Resource
 * 
 * Transforms Booking model for API consumption.
 * Includes full lifecycle information for AI agent processing.
 * 
 * @package App\Http\Resources
 * @version 1.0.0
 */
class BookingResource extends JsonResource
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
            'reference' => $this->reference,

            // Parties
            'employer_id' => $this->employer_id,
            'maid_id' => $this->maid_id,

            // Status
            'status' => $this->status,
            'payment_status' => $this->payment_status,

            // Schedule
            'start_date' => $this->start_date?->toIso8601String(),
            'end_date' => $this->end_date?->toIso8601String(),
            'schedule_type' => $this->schedule_type,
            'completed_at' => $this->completed_at?->toIso8601String(),

            // Financial
            'agreed_salary' => $this->agreed_salary,
            'total_amount' => $this->total_amount,
            'commission_amount' => $this->commission_amount,
            'maid_payout' => $this->maid_payout,
            'currency' => 'NGN',

            // Notes
            'notes' => $this->notes,
            'cancellation_reason' => $this->cancellation_reason,

            // AI Agent Processing
            'ai_processed' => (bool) $this->ai_processed,
            'ai_decisions' => $this->ai_decisions ?? [],

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Related Data (when loaded)
            'employer' => $this->whenLoaded('employer', function () {
                return [
                    'id' => $this->employer->id,
                    'name' => $this->employer->name,
                    'phone' => $this->employer->phone,
                ];
            }),

            'maid' => $this->whenLoaded('maid', function () {
                return [
                    'id' => $this->maid->id,
                    'name' => $this->maid->name,
                    'phone' => $this->maid->phone,
                    'profile' => $this->maid->maidProfile ? new MaidProfileResource($this->maid->maidProfile) : null,
                ];
            }),

            'reviews' => $this->whenLoaded('reviews', function () {
                return ReviewResource::collection($this->reviews);
            }),

            'disputes' => $this->whenLoaded('disputes', function () {
                return DisputeResource::collection($this->disputes);
            }),

            'payments' => $this->whenLoaded('payments', function () {
                return PaymentResource::collection($this->payments);
            }),

            // Actions available (computed)
            'actions' => $this->getAvailableActions(),
        ];
    }

    /**
     * Get available actions for this booking based on current status.
     * 
     * @return array<string>
     */
    private function getAvailableActions(): array
    {
        $actions = [];

        switch ($this->status) {
            case 'pending':
                $actions = ['accept', 'reject', 'cancel'];
                break;
            case 'accepted':
                $actions = ['start', 'cancel'];
                break;
            case 'active':
                $actions = ['complete', 'dispute'];
                break;
            case 'completed':
                $actions = ['review'];
                break;
            case 'cancelled':
                $actions = [];
                break;
        }

        return $actions;
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
                'resource_type' => 'booking',
                'version' => '1.0.0',
            ],
        ];
    }
}
