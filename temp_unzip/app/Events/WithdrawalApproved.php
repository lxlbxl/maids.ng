<?php

namespace App\Events;

use App\Models\MaidWallet;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WithdrawalApproved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public MaidWallet $wallet;
    public float $amount;
    public string $reference;
    public ?string $transactionReference;
    public ?string $processedBy;
    public array $context;

    /**
     * Create a new event instance.
     */
    public function __construct(
        MaidWallet $wallet,
        float $amount,
        string $reference,
        ?string $transactionReference = null,
        ?string $processedBy = null,
        array $context = []
    ) {
        $this->wallet = $wallet;
        $this->amount = $amount;
        $this->reference = $reference;
        $this->transactionReference = $transactionReference;
        $this->processedBy = $processedBy;
        $this->context = $context;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
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
            'reference' => $this->reference,
            'transaction_reference' => $this->transactionReference,
            'processed_by' => $this->processedBy,
            'available_balance' => $this->wallet->available_balance,
            'context' => $this->context,
            'event' => 'withdrawal.approved',
            'message' => 'Withdrawal request has been approved and processed',
        ];
    }
}
