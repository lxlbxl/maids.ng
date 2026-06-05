<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeoLocation extends Model
{
    protected $table = 'seo_locations';

    protected $fillable = [
        'type', 'name', 'slug', 'parent_id', 'state', 'tier',
        'description', 'demand_context', 'notable_estates', 'nearby_areas',
        'household_estimate', 'latitude', 'longitude',
        'meta_title', 'meta_description', 'is_active',
    ];

    protected $casts = [
        'notable_estates' => 'array',
        'nearby_areas'    => 'array',
        'is_active'       => 'boolean',
        'latitude'        => 'float',
        'longitude'       => 'float',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(SeoLocation::class, 'parent_id');
    }

    public function areas(): HasMany
    {
        return $this->hasMany(SeoLocation::class, 'parent_id');
    }

    public function pages(): HasMany
    {
        return $this->hasMany(SeoPage::class, 'location_id');
    }

    public function getUrlSegmentAttribute(): string
    {
        if ($this->type === 'area' && $this->parent) {
            return $this->slug . '-' . $this->parent->slug;
        }
        return $this->slug;
    }

    public function getFullNameAttribute(): string
    {
        if ($this->type === 'area' && $this->parent) {
            return "{$this->name}, {$this->parent->name}";
        }
        return $this->name;
    }
}
