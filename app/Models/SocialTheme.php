<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialTheme extends Model
{
    protected $table = 'social_themes';

    protected $fillable = [
        'name', 'slug', 'description', 'category',
        'is_active', 'tone', 'target_audience',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function posts()
    {
        return $this->hasMany(SocialPost::class, 'theme_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
