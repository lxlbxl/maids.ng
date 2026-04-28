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
        'status',
        'payment_type',
        'paid_at',
        'refunded_at',
        'gateway_response',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
            'gateway_response' => 'array',
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
