<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = [
        'employer_id',
        'maid_id',
        'booking_id',
        'rating',
        'comment',
        'is_flagged',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_flagged' => 'boolean',
        ];
    }

    public function employer()
    {
        return $this->belongsTo(User::class, 'employer_id');
    }

    public function maid()
    {
        return $this->belongsTo(User::class, 'maid_id');
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
