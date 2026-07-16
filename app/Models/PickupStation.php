<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;

class PickupStation extends Model
{
    protected $fillable = [
        'name', 'address', 'city', 'state', 'phone',
        'instructions', 'is_active', 'fee_pct', 'pickup_shipping_fee', 'access_pin',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'fee_pct'   => 'decimal:2',
        'pickup_shipping_fee' => 'decimal:2',
    ];

    /** Never expose the hashed PIN in JSON/arrays */
    protected $hidden = ['access_pin'];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function getFullAddressAttribute(): string
    {
        return collect([$this->address, $this->city, $this->state])
            ->filter()
            ->implode(', ');
    }

    public function verifyPin(string $plain): bool
    {
        return $this->access_pin && Hash::check($plain, $this->access_pin);
    }

    /**
     * Fee owed to this station for a single order.
     */
    public function feeForOrder(Order $order): float
    {
        return round((float) $order->grand_total * ((float) $this->fee_pct / 100), 2);
    }
}
