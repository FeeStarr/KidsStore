<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomOrderMeasurement extends Model
{
    /** What the tailor cuts to. */
    public const ROLE_PRODUCTION = 'production';

    /** Supplied so it can be set against the standard figures, never to cut from. */
    public const ROLE_COMPARISON = 'comparison';

    protected $fillable = [
        'custom_order_id', 'measurement_type', 'measurement_value', 'measurement_unit', 'role',
    ];

    protected $casts = [
        'measurement_value' => 'decimal:2',
    ];

    protected $attributes = [
        'role' => self::ROLE_PRODUCTION,
    ];

    public function customOrder(): BelongsTo
    {
        return $this->belongsTo(CustomOrder::class);
    }

    public function isProduction(): bool
    {
        return $this->role === self::ROLE_PRODUCTION;
    }

    public function isComparison(): bool
    {
        return $this->role === self::ROLE_COMPARISON;
    }
}
