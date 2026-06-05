<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentOutreachLog extends Model
{
    protected $table = 'agent_outreach_logs';

    protected $fillable = [
        'campaign_id', 'channel_identity_id', 'channel',
        'message_content', 'status', 'response_data',
        'sent_at', 'delivered_at', 'read_at', 'error_message',
    ];

    protected $casts = [
        'response_data' => 'array',
        'sent_at'       => 'datetime',
        'delivered_at'  => 'datetime',
        'read_at'       => 'datetime',
    ];

    public function campaign()
    {
        return $this->belongsTo(AgentCampaign::class, 'campaign_id');
    }

    public function identity()
    {
        return $this->belongsTo(AgentChannelIdentity::class, 'channel_identity_id');
    }
}
