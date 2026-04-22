<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployerPreference extends Model
{
    protected $fillable = [
        'employer_id',
        'help_types',
        'schedule',
        'urgency',
        'location',
        'state',
        'budget_min',
        'budget_max',
        'contact_name',
        'contact_phone',
        'contact_email',
        'selected_maid_id',
        'matching_status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'help_types' => 'array',
            'budget_min' => 'integer',
            'budget_max' => 'integer',
        ];
    }

    public function employer()
    {
        return $this->belongsTo(User::class, 'employer_id');
    }

    public function selectedMaid()
    {
        return $this->belongsTo(User::class, 'selected_maid_id');
    }

    public function payment()
    {
        return $this->hasOne(MatchingFeePayment::class, 'preference_id');
    }
}
