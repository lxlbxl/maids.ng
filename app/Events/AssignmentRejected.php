<?php

namespace App\Events;

use App\Models\MaidAssignment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AssignmentRejected
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public MaidAssignment $assignment;
    public int $employerId;
    public int $maidId;
    public ?string $rejectionReason;
    public float $refundAmount;
    public array $context;

    /**
     * Create a new event instance.
     */
    public function __construct(
        MaidAssignment $assignment,
        ?string $rejectionReason = null,
        float $refundAmount = 0,
        array $context = []
    ) {
        $this->assignment = $assignment;
        $this->employerId = $assignment->employer_id;
        $this->maidId = $assignment->maid_id;
        $this->rejectionReason = $rejectionReason;
        $this->refundAmount = $refundAmount;
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
            new PrivateChannel('admin.notifications'),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'assignment_id' => $this->assignment->id,
            'employer_id' => $this->employerId,
            'maid_id' => $this->maidId,
            'status' => 'rejected',
            'rejection_reason' => $this->rejectionReason,
            'refund_amount' => $this->refundAmount,
            'message' => 'Assignment has been rejected',
        ];
    }

    /**
     * Get the event type for webhooks.
     */
    public function getEventType(): string
    {
        return 'assignment.rejected';
    }

    /**
     * Get the payload for webhooks.
     */
    public function getPayload(): array
    {
        return [
            'assignment_id' => $this->assignment->id,
            'employer_id' => $this->employerId,
            'maid_id' => $this->maidId,
            'status' => 'rejected',
            'rejection_reason' => $this->rejectionReason,
            'refund_amount' => $this->refundAmount,
            'message' => 'Assignment has been rejected',
            'assignment' => [
                'id' => $this->assignment->id,
                'employer_id' => $this->assignment->employer_id,
                'maid_id' => $this->assignment->maid_id,
                'status' => $this->assignment->status,
                'created_at' => $this->assignment->created_at?->toIso8601String(),
                'rejected_at' => $this->assignment->rejected_at?->toIso8601String(),
            ],
        ];
    }
}
