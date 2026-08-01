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
    public const STATUS_SHIPPING_TO_STATION  = 'shipping to station';
    public const STATUS_OUT_FOR_DELIVERY     = 'out for delivery';
    public const STATUS_READY_FOR_PICKUP     = 'ready for pick up';
    public const STATUS_DELIVERED            = 'delivered';
    public const STATUS_CANCELLED            = 'cancelled';
    public const STATUS_PICKUP_WINDOW_EXPIRED = 'pickup window expired';

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
        'pickup_station_fee_total',
        'confirmed_at', 'processing_at', 'shipped_at', 'ready_for_pickup_at', 'delivered_at', 'cancelled_at',
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
        'pickup_station_fee_total' => 'decimal:2',
        'confirmed_at'            => 'datetime',
        'processing_at'           => 'datetime',
        'shipped_at'              => 'datetime',
        'ready_for_pickup_at'     => 'datetime',
        'delivered_at'            => 'datetime',
        'cancelled_at'            => 'datetime',
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

    public function paymentVerifications(): HasMany
    {
        return $this->hasMany(PaymentVerification::class);
    }

    public function latestPendingVerification(): ?PaymentVerification
    {
        return $this->paymentVerifications()
            ->whereIn('status', [PaymentVerification::STATUS_PENDING, PaymentVerification::STATUS_DELAYED])
            ->latest()
            ->first();
    }

    public function isVerificationPending(): bool
    {
        return $this->payment_status === 'verification_pending';
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
            self::STATUS_SHIPPING_TO_STATION,
            self::STATUS_OUT_FOR_DELIVERY,
            self::STATUS_READY_FOR_PICKUP,
            self::STATUS_DELIVERED,
            self::STATUS_CANCELLED,
            self::STATUS_PICKUP_WINDOW_EXPIRED,
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
            self::STATUS_SHIPPING_TO_STATION  => 'Shipping to Station',
            self::STATUS_OUT_FOR_DELIVERY     => 'Out for Delivery',
            self::STATUS_READY_FOR_PICKUP     => 'Ready for Pick Up',
            self::STATUS_DELIVERED            => 'Delivered',
            self::STATUS_CANCELLED            => 'Cancelled',
            self::STATUS_PICKUP_WINDOW_EXPIRED => 'Pickup Window Expired',
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
     * Status-specific estimates based on the order lifecycle.
     */
    public function getDeliveryWindowAttribute(): string
    {
        // Already delivered or cancelled — no estimate needed
        if (in_array($this->status, ['delivered', 'cancelled', 'pickup window expired'], true)) {
            return 'N/A';
        }

        // Ready for pickup — 4-day collection window
        if ($this->status === 'ready for pick up') {
            $readyAt = $this->ready_for_pickup_at ?? $this->shipped_at ?? $this->order_date;
            $latest = $readyAt->copy()->addDays(4);
            return 'Collect by ' . $latest->format('M d, Y');
        }

        // Use timestamp-based estimates when available
        $referenceDate = $this->processing_at ?? $this->confirmed_at ?? $this->order_date;

        $estimates = match ($this->status) {
            'ordered', 'pending confirmation', 'confirmed' =>
                ['earliest' => $referenceDate->copy()->addDays(2), 'latest' => $referenceDate->copy()->addDays(10)],
            'processing' =>
                ['earliest' => $referenceDate->copy()->addDays(2), 'latest' => $referenceDate->copy()->addDays(5)],
            'shipping to station', 'out for delivery' =>
                ['earliest' => $referenceDate->copy()->addDays(1), 'latest' => $referenceDate->copy()->addDays(3)],
            default =>
                ['earliest' => $referenceDate->copy()->addDays(2), 'latest' => $referenceDate->copy()->addDays(10)],
        };

        $earliest = $estimates['earliest'];
        $latest = $estimates['latest'];

        if ($earliest->format('M Y') === $latest->format('M Y')) {
            return $earliest->format('M d') . '–' . $latest->format('d, Y');
        }
        return $earliest->format('M d') . ' – ' . $latest->format('M d, Y');
    }

    /**
     * Number of days since the order became "ready for pick up".
     * Used by the pickup reminder scheduler.
     */
    public function getPickupDaysElapsed(): int
    {
        $readyAt = $this->ready_for_pickup_at ?? $this->shipped_at ?? $this->order_date;
        return (int) $readyAt->diffInDays(now());
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
