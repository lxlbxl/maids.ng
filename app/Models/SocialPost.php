<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialPost extends Model
{
    protected $table = 'social_posts';

    protected $fillable = [
        'theme_id', 'format', 'funnel_stage', 'hook', 'caption',
        'hashtags', 'call_to_action', 'image_description',
        'platforms', 'status', 'scheduled_at', 'published_at',
        'ai_model', 'prompt_tokens', 'completion_tokens',
        'total_tokens', 'estimated_cost_usd',
    ];

    protected $casts = [
        'hashtags'       => 'array',
        'platforms'       => 'array',
        'scheduled_at'    => 'datetime',
        'published_at'    => 'datetime',
    ];

    public function theme()
    {
        return $this->belongsTo(SocialTheme::class, 'theme_id');
    }

    public function media()
    {
        return $this->hasMany(SocialPostMedia::class, 'post_id');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeByStage($query, string $stage)
    {
        return $query->where('funnel_stage', $stage);
    }
}
