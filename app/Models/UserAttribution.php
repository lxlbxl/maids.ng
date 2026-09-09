<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAttribution extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'first_touch' => 'array',
            'last_touch' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function waClick()
    {
        return $this->belongsTo(WaClick::class);
    }
}
