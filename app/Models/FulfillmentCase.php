<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FulfillmentCase extends Model
{
    protected $fillable = [
        'employer_id',
        'maid_id',
        'preference_id',
        'assignment_id',
        'stage',
        'status',
        'agreed_salary',
        'maid_salary',
        'employer_salary',
        'salary_confirmed_at',
        'start_date',
        'start_time',
        'employer_address',
        'maid_arrived_day_one',
        'day_one_confirmed_at',
        'activated_at',
        'replacement_status',
        'fail_reason',
        'hours_in_stage',
        'last_contact_at',
        'next_action_due_at',
    ];

    public function employer()
    {
        return $this->belongsTo(User::class, 'employer_id');
    }

    public function maid()
    {
        return $this->belongsTo(User::class, 'maid_id');
    }

    public function events()
    {
        return $this->hasMany(FulfillmentEvent::class);
    }

    public function notes()
    {
        return $this->morphMany(AgentNote::class, 'entity');
    }
}
