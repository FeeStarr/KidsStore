<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PickupStationBankAccount extends Model
{
    protected $table = 'pickup_station_bank_accounts';

    protected $fillable = [
        'pickup_station_id', 'bank_name', 'bank_account_name', 'bank_account_number', 'instructions', 'is_active', 'is_default',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function pickupStation(): BelongsTo
    {
        return $this->belongsTo(PickupStation::class);
    }
}
