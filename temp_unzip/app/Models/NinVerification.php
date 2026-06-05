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
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
