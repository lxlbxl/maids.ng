<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StandaloneVerification extends Model
{
    protected $fillable = [
        'requester_id',
        'requester_name',
        'requester_email',
        'maid_nin',
        'maid_first_name',
        'maid_last_name',
        'maid_middle_name',
        'maid_dob',
        'maid_phone',
        'maid_email',
        'maid_gender',
        'amount',
        'payment_reference',
        'payment_status',
        'gateway',
        'verification_status',
        'verification_data',
        'confidence_score',
        'name_matched',
        'external_reference',
        'optional_fields',
        'report_path',
    ];

    protected $casts = [
        'verification_data' => 'array',
        'optional_fields' => 'array',
        'name_matched' => 'boolean',
        'confidence_score' => 'integer',
        'maid_dob' => 'date',
    ];

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * Get the full QoreID response data.
     */
    public function getQoreIdDataAttribute()
    {
        if (!$this->verification_data) {
            return null;
        }

        $data = is_array($this->verification_data)
            ? $this->verification_data
            : json_decode($this->verification_data, true);

        return $data['qoreid_data'] ?? null;
    }

    /**
     * Get the normalized verification data.
     */
    public function getNormalizedDataAttribute()
    {
        if (!$this->verification_data) {
            return null;
        }

        $data = is_array($this->verification_data)
            ? $this->verification_data
            : json_decode($this->verification_data, true);

        return $data['data'] ?? null;
    }

    /**
     * Check if verification was successful.
     */
    public function getIsVerifiedAttribute(): bool
    {
        return $this->verification_status === 'success'
            && $this->confidence_score >= 80
            && $this->name_matched;
    }
}