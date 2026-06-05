<?php

namespace App\Events;

use App\Models\SalarySchedule;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SalaryOverdue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public SalarySchedule $schedule;
    public int $daysOverdue;
    public ?string $lastReminderType;
    public array $context;

    /**
     * Create a new event instance.
     */
    public function __construct(
        SalarySchedule $schedule,
        int $daysOverdue,
        ?string $lastReminderType = null,
        array $context = []
    ) {
        $this->schedule = $schedule;
        $this->daysOverdue = $daysOverdue;
        $this->lastReminderType = $lastReminderType;
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
            new PrivateChannel('employer.' . $this->schedule->assignment->employer_id),
            new PrivateChannel('admin.notifications'),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'schedule_id' => $this->schedule->id,
            'assignment_id' => $this->schedule->assignment_id,
            'employer_id' => $this->schedule->assignment->employer_id,
            'maid_id' => $this->schedule->assignment->maid_id,
            'amount' => $this->schedule->amount,
            'due_date' => $this->schedule->due_date->toDateString(),
            'days_overdue' => $this->daysOverdue,
            'last_reminder_type' => $this->lastReminderType,
            'context' => $this->context,
            'event' => 'salary.overdue',
            'message' => "Salary payment is {$this->daysOverdue} days overdue",
        ];
    }
}
