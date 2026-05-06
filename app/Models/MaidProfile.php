<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaidProfile extends Model
{
    protected $fillable = [
        'user_id',
        'nin',
        'bio',
        'skills',
        'experience_years',
        'help_types',
        'schedule_preference',
        'expected_salary',
        'location',
        'state',
        'lga',
        'nin_verified',
        'background_verified',
        'availability_status',
        'rating',
        'total_reviews',
        'bank_name',
        'account_number',
        'account_name',
        'profile_completeness',
        'nin_report',
        'languages',
        'is_foreigner',
    ];

    protected function casts(): array
    {
        return [
            'skills' => 'array',
            'languages' => 'array',
            'help_types' => 'array',
            'nin_verified' => 'boolean',
            'background_verified' => 'boolean',
            'is_foreigner' => 'boolean',
            'expected_salary' => 'integer',
            'experience_years' => 'integer',
            'rating' => 'float',
            'total_reviews' => 'integer',
            'profile_completeness' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getMaidRole(): string
    {
        $types = $this->help_types ?? [];
        if (in_array('live-in', $types)) return 'Live-in Helper';
        if (in_array('nanny', $types)) return 'Nanny';
        if (in_array('cooking', $types)) return 'Cook';
        if (in_array('elderly-care', $types)) return 'Elderly Caregiver';
        if (in_array('driver', $types)) return 'Driver';
        return 'Housekeeper';
    }

    /**
     * Check if the maid is fully verified.
     */
    public function isVerified(): bool
    {
        return $this->nin_verified && $this->background_verified;
    }
}
