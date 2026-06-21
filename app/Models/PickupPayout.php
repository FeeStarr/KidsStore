<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PickupPayout extends Model
{
    protected $fillable = ['pickup_station_id','amount','created_by','reference','note','is_reversed','reversed_by','reversed_at'];

    protected $casts = [
        'is_reversed' => 'boolean',
        'reversed_at' => 'datetime',
    ];

    public function station(): BelongsTo
    {
        return $this->belongsTo(PickupStation::class, 'pickup_station_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PickupPayoutItem::class);
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'reversed_by');
    }
}
