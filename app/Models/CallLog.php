<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CallLog extends Model
{
    protected $fillable = [
        'vapi_call_id',
        'user_id',
        'phone',
        'call_type',
        'status',
        'duration_seconds',
        'transcript',
        'summary',
        'goal_achieved',
        'notes',
        'follow_up_action',
        'metadata',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'metadata'      => 'array',
        'goal_achieved' => 'boolean',
        'started_at'    => 'datetime',
        'ended_at'      => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
