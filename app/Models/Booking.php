<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable = [
        'employer_id',
        'maid_id',
        'preference_id',
        'status',
        'payment_status',
        'start_date',
        'end_date',
        'schedule_type',
        'agreed_salary',
        'notes',
        'cancellation_reason',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'agreed_salary' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function employer()
    {
        return $this->belongsTo(User::class, 'employer_id');
    }

    public function maid()
    {
        return $this->belongsTo(User::class, 'maid_id');
    }

    public function maidProfile()
    {
        return $this->hasOneThrough(MaidProfile::class, User::class, 'id', 'user_id', 'maid_id', 'id');
    }

    public function preference()
    {
        return $this->belongsTo(EmployerPreference::class, 'preference_id');
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }

    public function disputes()
    {
        return $this->hasMany(Dispute::class);
    }
}
