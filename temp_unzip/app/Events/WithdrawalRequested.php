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

class WithdrawalRequested
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public MaidWallet $wallet;
    public float $amount;
    public string $bankCode;
    public string $accountNumber;
    public string $accountName;
    public string $reference;
    public array $context;

    /**
     * Create a new event instance.
     */
    public function __construct(
        MaidWallet $wallet,
        float $amount,
        string $bankCode,
        string $accountNumber,
        string $accountName,
        string $reference,
        array $context = []
    ) {
        $this->wallet = $wallet;
        $this->amount = $amount;
        $this->bankCode = $bankCode;
        $this->accountNumber = $accountNumber;
        $this->accountName = $accountName;
        $this->reference = $reference;
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
            'bank_code' => $this->bankCode,
            'account_number' => $this->accountNumber,
            'account_name' => $this->accountName,
            'reference' => $this->reference,
            'available_balance' => $this->wallet->available_balance,
            'context' => $this->context,
            'event' => 'withdrawal.requested',
            'message' => 'Withdrawal request submitted for processing',
        ];
    }
}
