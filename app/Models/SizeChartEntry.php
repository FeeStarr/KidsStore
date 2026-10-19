<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SizeChartEntry extends Model
{
    protected $fillable = ['size_chart_id', 'label', 'fit', 'code', 'sort_order'];

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
     * Whether a fit tier has been assigned. Untilered entries are valid: they are the
     * existing standard cut, pending the tailor confirming which tier they represent.
     */
    public function hasFit(): bool
    {
        return in_array($this->fit, SizeChart::FITS, true);
    }

    public function fitLabel(): string
    {
        return $this->hasFit()
            ? (SizeChart::FIT_LABELS[$this->fit] ?? $this->fit)
            : 'Standard (no fitting assigned)';
    }

    /**
     * Customer-facing name, e.g. "2-3 years - Medium fitting". An untilered entry
     * reads as the age band alone rather than inventing a tier.
     */
    public function displayLabel(): string
    {
        return $this->hasFit() ? "{$this->label} - {$this->fitLabel()}" : $this->label;
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
