<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnboardingTouchpoint extends Model
{
    protected $fillable = [
        'journey_id',
        'user_id',
        'touchpoint_type',
        'channel',
        'status',
        'notes',
        'sent_at',
    ];

    public function journey()
    {
        return $this->belongsTo(OnboardingJourney::class, 'journey_id');
    }
}
