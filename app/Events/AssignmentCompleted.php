<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AssignmentCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly User $employer,
        public readonly User $maid,
        public readonly int $assignmentId,
    ) {
    }
}