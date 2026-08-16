<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomOrderMeasurement extends Model
{
    protected $fillable = [
        'custom_order_id', 'measurement_type', 'measurement_value', 'measurement_unit',
    ];

    protected $casts = [
        'measurement_value' => 'decimal:2',
    ];

    public function customOrder(): BelongsTo
    {
        return $this->belongsTo(CustomOrder::class);
    }
}
