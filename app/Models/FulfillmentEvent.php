<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FulfillmentEvent extends Model
{
    protected $fillable = [
        'fulfillment_case_id',
        'event_type',
        'from_stage',
        'to_stage',
        'notes',
        'actor_type',
    ];

    public function fulfillmentCase()
    {
        return $this->belongsTo(FulfillmentCase::class);
    }
}
