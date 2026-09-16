<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MatchingFeePayment extends Model
{
    protected $fillable = [
        'preference_id',
        'employer_id',
        'amount',
        'reference',
        'gateway',
        'tx_ref',
        'account_number',
        'account_bank',
        'account_name',
        'expires_at',
        'flutterwave_tx_id',
        // Flutterwave's own reference for the transfer — the NIBSS session id,
        // the same string the payer sees on their receipt. Must be fillable or
        // mass-assignment drops it silently and the unique index that prevents
        // double-recording a transfer never gets anything to work with.
        'flw_ref',
        'cancelled_reason',
        'status',
        'payment_type',
        'paid_at',
        'refunded_at',
        'gateway_response',
        'attribution',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
            'expires_at' => 'datetime',
            'gateway_response' => 'array',
            'attribution' => 'array',
        ];
    }

    public function preference()
    {
        return $this->belongsTo(EmployerPreference::class, 'preference_id');
    }

    public function employer()
    {
        return $this->belongsTo(User::class, 'employer_id');
    }
}
