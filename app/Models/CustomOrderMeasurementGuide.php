<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CustomOrderMeasurementGuide extends Model
{
    protected $table = 'custom_order_measurement_guides';

    protected $fillable = [
        'measurement_type', 'basis', 'name', 'description', 'illustration_path',
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

    /**
     * How-to-measure copy for a given basis. Body and finished-garment instructions are
     * genuinely different - measuring a child and measuring a laid-flat garment are not
     * interchangeable - so the basis must match the figure it sits beside.
     */
    public function scopeForBasis(Builder $query, string $basis): Builder
    {
        return $query->where('basis', $basis);
    }
}
