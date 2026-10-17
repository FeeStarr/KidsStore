<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SizeChartMeasurement extends Model
{
    protected $fillable = [
        'size_chart_entry_id', 'measurement_type', 'basis',
        'min_value', 'max_value', 'unit',
    ];

    protected $attributes = [
        'unit' => 'cm',
    ];

    protected $casts = [
        'min_value' => 'float',
        'max_value' => 'float',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(SizeChartEntry::class, 'size_chart_entry_id');
    }

    /**
     * Human label for the measurement type, falling back to the raw key.
     */
    public function label(): string
    {
        return CustomOrderMeasurementField::where('name', $this->measurement_type)
            ->value('label')
            ?? ucwords(str_replace('_', ' ', $this->measurement_type));
    }

    public function basisLabel(): string
    {
        return $this->basis === SizeChart::BASIS_BODY
            ? 'Body measurement'
            : 'Finished garment measurement';
    }

    /**
     * A range when both bounds exist, otherwise whichever single value was supplied.
     * Never interpolates or invents a missing figure.
     */
    public function displayValue(): string
    {
        if ($this->min_value !== null && $this->max_value !== null) {
            return $this->min_value === $this->max_value
                ? $this->min_value.' '.$this->unit
                : $this->min_value.'–'.$this->max_value.' '.$this->unit;
        }

        $value = $this->min_value ?? $this->max_value;

        return $value === null ? '' : $value.' '.$this->unit;
    }
}
