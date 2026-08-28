<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class GuestOtp extends Model
{
    protected $fillable = ['email', 'code', 'expires_at', 'verified', 'attempts'];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified'   => 'boolean',
    ];

    public function isExpired(): bool
    {
        return now()->isAfter($this->expires_at);
    }

    public function scopeValidForEmail(Builder $query, string $email): Builder
    {
        return $query->where('email', $email)
            ->where('verified', false)
            ->where('expires_at', '>', now());
    }
}
