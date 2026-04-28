<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Payment Resource
 * 
 * Transforms Payment/MatchingFeePayment model for API consumption.
 * 
 * @package App\Http\Resources
 * @version 1.0.0
 */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'booking_id' => $this->booking_id,
            'reference' => $this->reference,
            'amount' => $this->amount,
            'currency' => 'NGN',
            'status' => $this->status,
            'payment_method' => $this->payment_method,
            'gateway_response' => $this->when($request->user()?->hasRole('admin'), $this->gateway_response),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),

            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                ];
            }),
        ];
    }
}
