<?php

namespace App\Models;

class Maid extends User
{
    protected $table = 'users';

    public function getMorphClass(): string
    {
        return 'App\Models\User';
    }

    public function maidProfile()
    {
        return $this->hasOne(MaidProfile::class, 'user_id');
    }

    protected static function booted(): void
    {
        parent::booted();

        static::addGlobalScope('role', function ($query) {
            $query->whereHas('roles', fn($q) => $q->where('name', 'maid'));
        });
    }

    public function getFirstNameAttribute(): string
    {
        $parts = explode(' ', $this->name);
        return $parts[0] ?? '';
    }

    public function getLastNameAttribute(): string
    {
        $parts = explode(' ', $this->name);
        return $parts[1] ?? '';
    }

    public function getIsAvailableAttribute(): bool
    {
        return $this->maidProfile?->availability_status === 'available';
    }
}
