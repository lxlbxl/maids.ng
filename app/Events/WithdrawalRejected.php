<?php

namespace App\Events;

use App\Models\MaidWallet;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WithdrawalRejected
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public MaidWallet $wallet;
    public float $amount;
    public string $reason;
    public ?int $processedBy;

    /**
     * Create a new event instance.
     */
    public function __construct(
        MaidWallet $wallet,
        float $amount,
        string $reason,
        ?int $processedBy = null
    ) {
        $this->wallet = $wallet;
        $this->amount = $amount;
        $this->reason = $reason;
        $this->processedBy = $processedBy;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('maid.' . $this->wallet->maid_id),
            new PrivateChannel('admin.notifications'),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'wallet_id' => $this->wallet->id,
            'maid_id' => $this->wallet->maid_id,
            'amount' => $this->amount,
            'reason' => $this->reason,
            'processed_by' => $this->processedBy,
            'available_balance' => $this->wallet->available_balance,
            'event' => 'withdrawal.rejected',
            'message' => 'Withdrawal request has been rejected',
        ];
    }

    /**
     * Get the event type for webhooks.
     */
    public function getEventType(): string
    {
        return 'withdrawal.rejected';
    }

    /**
     * Get the payload for webhooks.
     */
    public function getPayload(): array
    {
        return [
            'wallet_id' => $this->wallet->id,
            'maid_id' => $this->wallet->maid_id,
            'amount' => $this->amount,
            'reason' => $this->reason,
            'processed_by' => $this->processedBy,
            'available_balance' => $this->wallet->available_balance,
            'message' => 'Withdrawal request has been rejected',
            'wallet' => [
                'id' => $this->wallet->id,
                'maid_id' => $this->wallet->maid_id,
                'balance' => $this->wallet->balance,
                'available_balance' => $this->wallet->available_balance,
                'pending_withdrawal' => $this->wallet->pending_withdrawal,
            ],
        ];
    }
}
