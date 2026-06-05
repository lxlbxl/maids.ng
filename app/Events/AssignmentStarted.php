<?php

namespace App\Events;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AssignmentStarted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly User $employer,
        public readonly User $maid,
        public readonly int $assignmentId,
        public readonly Carbon $startDate,
    ) {
    }
}