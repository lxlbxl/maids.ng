<?php

namespace App\Events;

use App\Models\MaidAssignment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AssignmentCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly MaidAssignment $assignment,
    ) {
    }

    /**
     * Get the event type for webhooks.
     */
    public function getEventType(): string
    {
        return 'assignment.completed';
    }

    /**
     * Get the payload for webhooks.
     */
    public function getPayload(): array
    {
        return [
            'assignment_id' => $this->assignment->id,
            'employer_id' => $this->assignment->employer_id,
            'maid_id' => $this->assignment->maid_id,
            'status' => 'completed',
            'message' => 'Assignment has been completed',
            'assignment' => [
                'id' => $this->assignment->id,
                'employer_id' => $this->assignment->employer_id,
                'maid_id' => $this->assignment->maid_id,
                'status' => $this->assignment->status,
                'created_at' => $this->assignment->created_at?->toIso8601String(),
                'completed_at' => $this->assignment->completed_at?->toIso8601String(),
            ],
        ];
    }
}
