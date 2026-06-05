<?php

namespace App\Listeners;

use App\Events\AssignmentAccepted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateMaidAvailabilityOnAccept implements ShouldQueue
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
    public function handle(AssignmentAccepted $event): void
    {
        $assignment = $event->assignment;
        $maid = $assignment->maid;

        // Update maid availability to not available
        $maid->update([
            'is_available' => false,
            'current_assignment_id' => $assignment->id,
        ]);

        \Log::info('Maid availability updated on assignment acceptance', [
            'maid_id' => $maid->id,
            'assignment_id' => $assignment->id,
            'is_available' => false,
        ]);
    }
}
