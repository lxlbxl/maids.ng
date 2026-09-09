<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadAttribution extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'attribution' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function waClick()
    {
        return $this->belongsTo(WaClick::class);
    }
}
