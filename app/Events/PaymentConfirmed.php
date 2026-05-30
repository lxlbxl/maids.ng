<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentConfirmed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $reference,
        public readonly int $amount,
        public readonly string $type,
    ) {
    }

    /**
     * Get the event type for webhooks.
     */
    public function getEventType(): string
    {
        return 'payment.successful';
    }

    /**
     * Get the payload for webhooks.
     */
    public function getPayload(): array
    {
        return [
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'reference' => $this->reference,
            'amount' => $this->amount,
            'type' => $this->type,
            'payment' => [
                'reference' => $this->reference,
                'amount' => $this->amount,
                'type' => $this->type,
                'created_at' => now()->toIso8601String(),
            ],
        ];
    }
}