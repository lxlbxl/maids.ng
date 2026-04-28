<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StandaloneVerification extends Model
{
    protected $fillable = [
        'requester_id',
        'maid_nin',
        'maid_first_name',
        'maid_last_name',
        'amount',
        'payment_reference',
        'payment_status',
        'gateway',
        'verification_status',
        'verification_data',
        'report_path'
    ];

    protected $casts = [
        'verification_data' => 'array',
    ];

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }
}
