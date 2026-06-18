<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Order extends Model
{
    // Order Status Constants
    public const STATUS_PENDING              = 'pending';           // legacy — not a valid ENUM value, kept for BC
    public const STATUS_ORDERED              = 'ordered';
    public const STATUS_PENDING_CONFIRMATION = 'pending confirmation';
    public const STATUS_CONFIRMED            = 'confirmed';
    public const STATUS_PROCESSING           = 'processing';
    public const STATUS_OUT_FOR_DELIVERY     = 'out for delivery';
    public const STATUS_READY_FOR_PICKUP     = 'ready for pick up';
    public const STATUS_DELIVERED            = 'delivered';
    public const STATUS_CANCELLED            = 'cancelled';

    // Delivery Method Constants
    public const DELIVERY_METHOD_PICKUP = 'pickup';
    public const DELIVERY_METHOD_DELIVERY = 'delivery';

    protected $fillable = [
        'reference', 'customer_id', 'order_date', 'status', 'delivery_method', 'payment_status',
        'pickup_station_id', 'delivery_address',
        'courier_name', 'tracking_number', 'tracking_url',
        'total_amount',
        'subtotal', 'discount', 'shipping_fee', 'grand_total', 'amount_paid',
        'note', 'expected_delivery_date',
    ];

    protected $casts = [
        'order_date'              => 'date',
        'expected_delivery_date'  => 'date',
        'total_amount'            => 'decimal:2',
        'subtotal'                => 'decimal:2',
        'discount'                => 'decimal:2',
        'shipping_fee'            => 'decimal:2',
        'grand_total'             => 'decimal:2',
        'amount_paid'             => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function pickupStation(): BelongsTo
    {
        return $this->belongsTo(PickupStation::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class)->latest();
    }

    public function refundRequests(): HasMany
    {
        return $this->hasMany(RefundRequest::class)->latest();
    }

    /** The most recent pending or successful OPay transaction */
    public function activeTransaction(): ?PaymentTransaction
    {
        return $this->paymentTransactions()
            ->whereIn('status', ['pending', 'success'])
            ->first();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function getBalanceAttribute(): float
    {
        return (float) $this->total_amount - (float) $this->amount_paid;
    }

    /**
     * Get all available order statuses
     */
    public static function getAvailableStatuses(): array
    {
        return [
            self::STATUS_ORDERED,
            self::STATUS_PENDING_CONFIRMATION,
            self::STATUS_CONFIRMED,
            self::STATUS_PROCESSING,
            self::STATUS_OUT_FOR_DELIVERY,
            self::STATUS_READY_FOR_PICKUP,
            self::STATUS_DELIVERED,
            self::STATUS_CANCELLED,
        ];
    }

    /**
     * Check if order is for home/specified delivery
     */
    public function isForDelivery(): bool
    {
        return $this->delivery_method === self::DELIVERY_METHOD_DELIVERY;
    }

    /**
     * Check if order is for pick up
     */
    public function isForPickup(): bool
    {
        return $this->delivery_method === self::DELIVERY_METHOD_PICKUP;
    }

    /**
     * Get status label for display
     */
    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_ORDERED              => 'Ordered',
            self::STATUS_PENDING_CONFIRMATION => 'Pending Confirmation',
            self::STATUS_CONFIRMED            => 'Confirmed',
            self::STATUS_PROCESSING           => 'Processing',
            self::STATUS_OUT_FOR_DELIVERY     => 'Out for Delivery',
            self::STATUS_READY_FOR_PICKUP     => 'Ready for Pick Up',
            self::STATUS_DELIVERED            => 'Delivered',
            self::STATUS_CANCELLED            => 'Cancelled',
            default                           => ucfirst($this->status),
        };
    }

    public function getDeliveryMethodLabel(): string
    {
        return match ($this->delivery_method) {
            self::DELIVERY_METHOD_DELIVERY => 'Home Delivery',
            self::DELIVERY_METHOD_PICKUP   => 'Pick Up',
            default                        => ucfirst($this->delivery_method),
        };
    }

    /**
     * Human-readable delivery window shown to customers.
     * Based on expected_delivery_date ± a couple of days, or order_date + 3–10 days.
     */
    public function getDeliveryWindowAttribute(): string
    {
        if ($this->expected_delivery_date) {
            $earliest = $this->expected_delivery_date->copy()->subDays(1);
            $latest   = $this->expected_delivery_date->copy()->addDays(1);
            if ($earliest->format('M Y') === $latest->format('M Y')) {
                return $earliest->format('M d') . '–' . $latest->format('d, Y');
            }
            return $earliest->format('M d') . ' – ' . $latest->format('M d, Y');
        }

        // Fallback: 3–10 days from order date
        $earliest = $this->order_date->copy()->addDays(3);
        $latest   = $this->order_date->copy()->addDays(10);
        if ($earliest->format('M Y') === $latest->format('M Y')) {
            return $earliest->format('M d') . '–' . $latest->format('d, Y');
        }
        return $earliest->format('M d') . ' – ' . $latest->format('M d, Y');
    }

    /**
     * Helper methods for status transitions
     */
    public function markAsOrdered(): void
    {
        $this->update(['status' => self::STATUS_ORDERED]);
    }

    public function markAsConfirmed(): void
    {
        $this->update(['status' => self::STATUS_CONFIRMED]);
    }

    public function markAsProcessing(): void
    {
        $this->update(['status' => self::STATUS_PROCESSING]);
    }

    public function markAsOutForDelivery(): void
    {
        $this->update(['status' => self::STATUS_OUT_FOR_DELIVERY]);
    }

    public function markAsReadyForPickup(): void
    {
        $this->update(['status' => self::STATUS_READY_FOR_PICKUP]);
    }

    public function markAsDelivered(): void
    {
        $this->update(['status' => self::STATUS_DELIVERED]);
    }

    public function markAsCancelled(): void
    {
        $this->update(['status' => self::STATUS_CANCELLED]);
    }
}
