<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'product_variant_id', 'quantity',
        'cancelled_quantity', 'cancelled_at',
        'unit_price', 'original_unit_price', 'landed_unit_cost', 'discount', 'discount_amount', 'deal_id', 'coupon_id',
        'coupon_discount', 'line_total',
        'selected_age_group', 'selected_size',
        'pickup_station_fee', 'pickup_station_fee_paid', 'pickup_station_fee_paid_at',
        'pickup_status', 'pickup_status_changed_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'cancelled_quantity' => 'integer',
        'cancelled_at' => 'datetime',
        'unit_price' => 'decimal:2',
        'original_unit_price' => 'decimal:2',
        'landed_unit_cost' => 'decimal:2',
        'discount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'coupon_discount' => 'decimal:2',
        'line_total' => 'decimal:2',
        'pickup_station_fee' => 'decimal:2',
        'pickup_station_fee_paid' => 'boolean',
        'pickup_station_fee_paid_at' => 'datetime',
        'pickup_status_changed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class, 'deal_id');
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, 'coupon_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return ucfirst(str_replace('_', ' ', $this->pickup_status ?? 'pending'));
    }

    public function isPending(): bool
    {
        return $this->pickup_status === 'pending';
    }

    public function isReceived(): bool
    {
        return $this->pickup_status === 'received';
    }

    public function isReady(): bool
    {
        return $this->pickup_status === 'ready for pickup';
    }

    public function isPickedUp(): bool
    {
        return $this->pickup_status === 'picked_up';
    }

    public function remainingQuantity(): int
    {
        return max(0, (int) $this->quantity - (int) $this->cancelled_quantity);
    }

    public function isFullyCancelled(): bool
    {
        return $this->remainingQuantity() === 0 && (int) $this->quantity > 0;
    }

    /**
     * Commission amount for this item (% of line_total from settings).
     * Only calculated when item is picked_up.
     */
    public function getCommissionAttribute(): float
    {
        if (! $this->isPickedUp()) {
            return 0.0;
        }
        $rate = (float) Setting::get('commission_rate', 10);
        return round((float) $this->line_total * ($rate / 100), 2);
    }
}
