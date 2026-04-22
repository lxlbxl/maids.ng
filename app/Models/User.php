<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'avatar',
        'status',
        'location',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // ── Relationships ──

    public function maidProfile()
    {
        return $this->hasOne(MaidProfile::class);
    }

    public function employerPreferences()
    {
        return $this->hasMany(EmployerPreference::class, 'employer_id');
    }

    public function bookingsAsEmployer()
    {
        return $this->hasMany(Booking::class, 'employer_id');
    }

    public function bookingsAsMaid()
    {
        return $this->hasMany(Booking::class, 'maid_id');
    }

    public function reviewsGiven()
    {
        return $this->hasMany(Review::class, 'employer_id');
    }

    public function reviewsReceived()
    {
        return $this->hasMany(Review::class, 'maid_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    // ── Helpers ──

    public function isMaid(): bool
    {
        return $this->hasRole('maid');
    }

    public function isEmployer(): bool
    {
        return $this->hasRole('employer');
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function getAverageRating(): float
    {
        return $this->reviewsReceived()->avg('rating') ?? 0;
    }
}
