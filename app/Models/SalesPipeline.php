<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesPipeline extends Model
{
    protected $fillable = [
        'user_id',
        'funnel_stage',
        'lead_score',
        'actions_taken',
        'last_outreach_at',
        'outreach_count',
        'outreach_channel',
        'last_message_preview',
        'notes',
    ];

    protected $casts = [
        'actions_taken' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
