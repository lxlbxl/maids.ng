<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnboardingJourney extends Model
{
    protected $fillable = [
        'user_id',
        'status',
        'current_step',
        'completion_pct',
        'last_activity_at',
        'converted_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function touchpoints()
    {
        return $this->hasMany(OnboardingTouchpoint::class, 'journey_id');
    }
}
