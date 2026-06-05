<?php

namespace App\Events;

use App\Models\MaidAssignment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AssignmentAccepted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public MaidAssignment $assignment;
    public int $employerId;
    public int $maidId;
    public array $context;

    /**
     * Create a new event instance.
     */
    public function __construct(MaidAssignment $assignment, array $context = [])
    {
        $this->assignment = $assignment;
        $this->employerId = $assignment->employer_id;
        $this->maidId = $assignment->maid_id;
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
            'assignment_id' => $this->assignment->id,
            'employer_id' => $this->employerId,
            'maid_id' => $this->maidId,
            'status' => 'accepted',
            'message' => 'Assignment has been accepted',
        ];
    }
}
