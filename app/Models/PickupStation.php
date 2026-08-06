<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Hash;

class PickupStation extends Model
{
    protected $fillable = [
        'name', 'address', 'city', 'state', 'phone', 'email',
        'instructions', 'is_active', 'fee_pct', 'pickup_shipping_fee', 'access_pin',
        'is_available', 'unavailability_reason',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_available' => 'boolean',
        'fee_pct'   => 'decimal:2',
        'pickup_shipping_fee' => 'decimal:2',
    ];

    /** Never expose the hashed PIN in JSON/arrays */
    protected $hidden = ['access_pin'];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(PickupStationBankAccount::class);
    }

    public function defaultBankAccount()
    {
        return $this->bankAccounts()->where('is_default', true)->first();
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

    /**
     * Check if station is operational (active and available).
     */
    public function isOperational(): bool
    {
        return $this->is_active && $this->is_available;
    }

    /**
     * Get orders assigned to this station with pending items.
     */
    public function ordersWithPendingItems()
    {
        return $this->orders()
            ->whereHas('items', fn($q) => $q->where('pickup_status', 'pending'))
            ->with(['items' => fn($q) => $q->where('pickup_status', 'pending')])
            ->latest();
    }

    /**
     * Get all items at this station that are received but not yet ready.
     */
    public function receivedItems()
    {
        return OrderItem::whereHas('order', fn($q) => $q->where('pickup_station_id', $this->id))
            ->where('pickup_status', 'received')
            ->with(['order', 'product', 'variant'])
            ->get();
    }

    /**
     * Get items ready for pickup at this station.
     */
    public function readyItems()
    {
        return OrderItem::whereHas('order', fn($q) => $q->where('pickup_station_id', $this->id))
            ->where('pickup_status', 'ready')
            ->with(['order', 'product', 'variant'])
            ->get();
    }

    /**
     * Get picked up items (for payout calculation).
     */
    public function pickedUpItems()
    {
        return OrderItem::whereHas('order', fn($q) => $q->where('pickup_station_id', $this->id))
            ->where('pickup_status', 'picked_up')
            ->with(['order', 'product', 'variant'])
            ->get();
    }

    /**
     * Calculate total commission for all picked up items (per-order cap: min ₦500, max ₦2,000).
     */
    public function totalCommission(): float
    {
        $items = $this->pickedUpItems()->get();

        $byOrder = [];
        foreach ($items as $item) {
            $byOrder[$item->order_id] = ($byOrder[$item->order_id] ?? 0) + $item->commission;
        }

        $total = 0.0;
        foreach ($byOrder as $orderCommission) {
            $total += max(500.0, min(2000.0, $orderCommission));
        }

        return round($total, 2);
    }
}
