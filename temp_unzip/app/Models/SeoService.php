<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoService extends Model
{
    protected $table = 'seo_services';

    protected $fillable = [
        'name', 'slug', 'plural', 'also_known_as', 'short_description',
        'full_description', 'duties', 'who_needs_this', 'what_to_look_for',
        'salary_min', 'salary_max', 'salary_by_city',
        'live_in_available', 'part_time_available', 'nin_required',
        'schema_service_type', 'meta_title_template', 'meta_description_template',
        'demand_index', 'is_active',
    ];

    protected $casts = [
        'also_known_as'       => 'array',
        'salary_by_city'      => 'array',
        'live_in_available'   => 'boolean',
        'part_time_available' => 'boolean',
        'nin_required'        => 'boolean',
        'is_active'           => 'boolean',
    ];

    public function getSalaryForCity(string $citySlug): array
    {
        $cityData = $this->salary_by_city[$citySlug] ?? null;
        return [
            'min' => $cityData['min'] ?? $this->salary_min,
            'max' => $cityData['max'] ?? $this->salary_max,
        ];
    }
}
