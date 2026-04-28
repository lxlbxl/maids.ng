<?php

namespace App\Events;

use App\Models\AiMatchingQueue;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MatchingJobCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public AiMatchingQueue $job;
    public array $results;
    public ?int $matchesFound;
    public float $processingTime;
    public array $context;

    /**
     * Create a new event instance.
     */
    public function __construct(
        AiMatchingQueue $job,
        array $results = [],
        ?int $matchesFound = null,
        float $processingTime = 0,
        array $context = []
    ) {
        $this->job = $job;
        $this->results = $results;
        $this->matchesFound = $matchesFound;
        $this->processingTime = $processingTime;
        $this->context = $context;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('admin.notifications'),
            new PrivateChannel('admin.matching-queue'),
        ];

        // Also notify the employer if this was a specific employer request
        if ($this->job->employer_id) {
            $channels[] = new PrivateChannel('employer.' . $this->job->employer_id);
        }

        return $channels;
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'job_id' => $this->job->id,
            'employer_id' => $this->job->employer_id,
            'job_type' => $this->job->job_type,
            'status' => $this->job->status,
            'results' => $this->results,
            'matches_found' => $this->matchesFound,
            'processing_time' => $this->processingTime,
            'processed_at' => $this->job->processed_at?->toIso8601String(),
            'context' => $this->context,
            'event' => 'matching.job.completed',
            'message' => $this->matchesFound !== null
                ? "AI matching completed with {$this->matchesFound} matches found"
                : 'AI matching job completed',
        ];
    }
}
