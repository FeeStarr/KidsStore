<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CustomOrderMeasurementGuide extends Model
{
    protected $table = 'custom_order_measurement_guides';

    protected $fillable = [
        'measurement_type', 'name', 'description', 'illustration_path',
        'video_url', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
