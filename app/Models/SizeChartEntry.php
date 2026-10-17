<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SizeChartEntry extends Model
{
    protected $fillable = ['size_chart_id', 'label', 'code', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    public function chart(): BelongsTo
    {
        return $this->belongsTo(SizeChart::class, 'size_chart_id');
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(SizeChartMeasurement::class);
    }

    /**
     * Which bases this entry actually has figures for.
     *
     * The public guide shows only these, so a chart with finished-garment data alone
     * never implies that body measurements were also measured.
     */
    public function availableBases(): array
    {
        return $this->measurements
            ->pluck('basis')
            ->unique()
            ->values()
            ->all();
    }

    public function hasBasis(string $basis): bool
    {
        return in_array($basis, $this->availableBases(), true);
    }
}
