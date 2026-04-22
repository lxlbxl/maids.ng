<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    protected $fillable = [
        'user_id',
        'subject',
        'message',
        'status',
        'priority',
        'agent_handled',
        'agent_response',
    ];

    protected $casts = [
        'agent_handled' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
