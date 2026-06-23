<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NinVerification extends Model
{
    protected $fillable = [
        'user_id',
        'nin_hash',
        'status',
        'confidence_score',
        'external_reference',
        'review_notes',
        'submitted_at',
        'reviewed_at',
        'qoreid_payload',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'qoreid_payload' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
