<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoPage extends Model
{
    protected $table = 'seo_pages';

    protected $fillable = [
        'page_type', 'url_path', 'location_id', 'service_id',
        'content_blocks', 'meta_title', 'meta_description', 'h1',
        'canonical_url', 'schema_markup', 'page_status', 'content_score',
        'impressions', 'clicks', 'avg_position', 'ctr',
        'content_generated_at', 'last_indexed_at',
    ];

    protected $casts = [
        'content_blocks'        => 'array',
        'schema_markup'         => 'array',
        'content_generated_at'  => 'datetime',
        'last_indexed_at'       => 'datetime',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(SeoLocation::class, 'location_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(SeoService::class, 'service_id');
    }
}
