<?php

namespace App\Events;

use App\Models\SalaryPayment;
use App\Models\SalarySchedule;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SalaryPaymentProcessed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public SalaryPayment $payment;
    public SalarySchedule $schedule;
    public int $employerId;
    public int $maidId;
    public float $amount;
    public array $context;

    /**
     * Create a new event instance.
     */
    public function __construct(SalaryPayment $payment, array $context = [])
    {
        $this->payment = $payment;
        $this->schedule = $payment->salarySchedule;
        $this->employerId = $payment->employer_id;
        $this->maidId = $payment->maid_id;
        $this->amount = $payment->amount;
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
            new PrivateChannel('employer.' . $this->employerId),
            new PrivateChannel('maid.' . $this->maidId),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'payment_id' => $this->payment->id,
            'schedule_id' => $this->schedule->id,
            'employer_id' => $this->employerId,
            'maid_id' => $this->maidId,
            'amount' => $this->amount,
            'payment_method' => $this->payment->payment_method,
            'status' => 'processed',
            'message' => 'Salary payment has been processed',
        ];
    }

    /**
     * Get the event type for webhooks.
     */
    public function getEventType(): string
    {
        return 'salary.paid';
    }

    /**
     * Get the payload for webhooks.
     */
    public function getPayload(): array
    {
        return [
            'payment_id' => $this->payment->id,
            'schedule_id' => $this->schedule->id,
            'employer_id' => $this->employerId,
            'maid_id' => $this->maidId,
            'amount' => $this->amount,
            'status' => 'paid',
            'message' => 'Salary payment has been processed',
            'payment' => [
                'id' => $this->payment->id,
                'employer_id' => $this->payment->employer_id,
                'maid_id' => $this->payment->maid_id,
                'amount' => $this->payment->amount,
                'status' => $this->payment->status,
                'due_date' => $this->payment->due_date?->toIso8601String(),
                'paid_at' => $this->payment->paid_at?->toIso8601String(),
            ],
        ];
    }
}
