<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CsCase extends Model
{
    protected $fillable = [
        'assignment_id',
        'employer_id',
        'maid_id',
        'health_status',
        'satisfaction_score',
        'last_contact_at',
        'next_appraisal_due',
        'status',
    ];

    public function employer()
    {
        return $this->belongsTo(User::class, 'employer_id');
    }

    public function maid()
    {
        return $this->belongsTo(User::class, 'maid_id');
    }

    public function assignment()
    {
        return $this->belongsTo(MaidAssignment::class, 'assignment_id');
    }

    public function tickets()
    {
        return $this->hasMany(SupportTicket::class, 'cs_case_id');
    }
}
