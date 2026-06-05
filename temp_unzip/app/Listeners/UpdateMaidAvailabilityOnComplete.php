<?php

namespace App\Listeners;

use App\Events\AssignmentCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateMaidAvailabilityOnComplete implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(AssignmentCompleted $event): void
    {
        $assignment = $event->assignment;
        $maid = $assignment->maid;

        // Update maid availability to available again
        $maid->update([
            'is_available' => true,
            'current_assignment_id' => null,
        ]);

        \Log::info('Maid availability updated on assignment completion', [
            'maid_id' => $maid->id,
            'assignment_id' => $assignment->id,
            'is_available' => true,
        ]);
    }
}
