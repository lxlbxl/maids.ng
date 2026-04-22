<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentActivityLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'requires_review' => 'boolean',
        'overridden' => 'boolean',
        'reviewed_at' => 'datetime',
    ];

    /**
     * Get the related model (User, Booking, etc.) for this log entry.
     */
    public function subject()
    {
        return $this->morphTo();
    }
}
