<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dispute extends Model
{
    protected $fillable = [
        'booking_id',
        'filed_by',
        'reason',
        'evidence',
        'agent_recommendation',
        'resolution',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'filed_by');
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function filedBy()
    {
        return $this->belongsTo(User::class, 'filed_by');
    }
}
