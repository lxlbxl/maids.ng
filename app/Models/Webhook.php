<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Webhook extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'url',
        'secret',
        'events',
        'timeout',
        'max_retries',
        'is_active',
        'verify_ssl',
    ];

    protected $casts = [
        'events' => 'array',
        'is_active' => 'boolean',
        'verify_ssl' => 'boolean',
        'timeout' => 'integer',
        'max_retries' => 'integer',
    ];

    /**
     * Scope a query to only include active webhooks.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
