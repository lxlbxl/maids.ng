<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentCampaign extends Model
{
    protected $table = 'agent_campaigns';

    protected $fillable = [
        'name', 'slug', 'description', 'trigger_type', 'preferred_channel',
        'is_active', 'schedule_cron', 'max_contacts_per_day',
        'message_template', 'last_run_at', 'next_run_at', 'created_by',
    ];

    protected $casts = [
        'is_active'     => 'boolean',
        'last_run_at'   => 'datetime',
        'next_run_at'   => 'datetime',
    ];

    public function logs(): HasMany
    {
        return $this->hasMany(AgentOutreachLog::class, 'campaign_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
