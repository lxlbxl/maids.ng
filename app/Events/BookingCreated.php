<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $booking;

    /**
     * Create a new event instance.
     */
    public function __construct(Booking $booking)
    {
        $this->booking = $booking;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('maid.' . $this->booking->maid_id),
            new PrivateChannel('admin.notifications'),
        ];
    }

    public function broadcastAs()
    {
        return 'booking.created';
    }

    /**
     * Get the event type for webhooks.
     */
    public function getEventType(): string
    {
        return 'booking.created';
    }

    /**
     * Get the payload for webhooks.
     */
    public function getPayload(): array
    {
        return [
            'booking_id' => $this->booking->id,
            'employer_id' => $this->booking->employer_id,
            'maid_id' => $this->booking->maid_id,
            'status' => $this->booking->status,
            'amount' => $this->booking->amount,
            'booking' => [
                'id' => $this->booking->id,
                'employer_id' => $this->booking->employer_id,
                'maid_id' => $this->booking->maid_id,
                'status' => $this->booking->status,
                'amount' => $this->booking->amount,
                'created_at' => $this->booking->created_at?->toIso8601String(),
            ],
        ];
    }
}
