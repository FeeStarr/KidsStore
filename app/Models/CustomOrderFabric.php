<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CustomOrderFabric extends Model
{
    protected $fillable = ['name', 'availability', 'is_active', 'sort_order'];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    const AVAILABILITY_AVAILABLE = 'available';
    const AVAILABILITY_UNAVAILABLE = 'unavailable';
    const AVAILABILITY_DISCONTINUED = 'discontinued';

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('availability', self::AVAILABILITY_AVAILABLE)
            ->orderBy('sort_order');
    }

    public function isAvailable(): bool
    {
        return $this->is_active && $this->availability === self::AVAILABILITY_AVAILABLE;
    }
}
