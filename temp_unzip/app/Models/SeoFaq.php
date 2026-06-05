<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoFaq extends Model
{
    protected $table = 'seo_faqs';

    protected $fillable = [
        'question', 'answer', 'short_answer', 'slug',
        'service_id', 'location_id', 'category',
        'embedded_on_page_types', 'targets_paa',
        'estimated_monthly_searches', 'is_active',
    ];

    protected $casts = [
        'embedded_on_page_types' => 'array',
        'targets_paa'            => 'boolean',
        'is_active'              => 'boolean',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(SeoService::class, 'service_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(SeoLocation::class, 'location_id');
    }
}
