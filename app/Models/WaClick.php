<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaClick extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function resolvedUser()
    {
        return $this->belongsTo(User::class, 'resolved_user_id');
    }
}
