<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RefundRequest extends Model
{
    // Refund window in days after delivery (default, used for reasons not listed below)
    public const REFUND_WINDOW_DAYS = 7;

    /**
     * Per-reason return time limits (in hours) counted from delivery.
     * Reasons not listed here fall back to REFUND_WINDOW_DAYS.
     */
    public const REASON_TIME_LIMITS = [
        'wrong_item'       => 5 * 24,   // 5 business days ≈ 5 calendar days
        'wrong_size'       => 5 * 24,   // 5 business days
        'wrong_color'      => 5 * 24,   // 5 business days
        'damaged'          => 48,       // 48 hours
        'missing_item'     => 24,       // 24 hours
        'incomplete_order' => 5 * 24,   // 5 business days
        'not_as_described' => 5 * 24,   // 5 business days
        'changed_mind'     => 3 * 24,   // 3 business days
    ];

    // ── Statuses ──────────────────────────────────────────────────────────────
    public const STATUS_REFUND_REQUIRED      = 'refund_required';
    public const STATUS_REQUESTED            = 'requested';
    public const STATUS_PENDING_REVIEW      = 'pending_review';
    public const STATUS_AWAITING_EVIDENCE   = 'awaiting_evidence';
    public const STATUS_APPROVED            = 'approved';
    public const STATUS_REJECTED            = 'rejected';
    public const STATUS_AWAITING_SHIPMENT   = 'awaiting_shipment';
    public const STATUS_IN_TRANSIT          = 'in_transit';
    public const STATUS_RECEIVED            = 'received';
    public const STATUS_INSPECTION          = 'inspection';
    public const STATUS_REFUND_APPROVED     = 'refund_approved';
    public const STATUS_REFUND_PROCESSING   = 'refund_processing';
    public const STATUS_REFUNDED            = 'refunded';
    public const STATUS_REFUND_FAILED       = 'refund_failed';
    public const STATUS_REPLACEMENT_APPROVED = 'replacement_approved';
    public const STATUS_REPLACEMENT_SHIPPED = 'replacement_shipped';
    public const STATUS_REPLACEMENT_DELIVERED = 'replacement_delivered';
    public const STATUS_COMPLETED           = 'completed';
    public const STATUS_CANCELLED           = 'cancelled';
    public const STATUS_RETURN_COLLECTED    = 'return_collected';

    // Alias for backward compatibility
    public const STATUS_PENDING  = self::STATUS_PENDING_REVIEW;
    public const STATUS_FAILED   = self::STATUS_REFUND_FAILED;

    public const STATUSES = [
        self::STATUS_REFUND_REQUIRED     => 'Refund Required',
        self::STATUS_REQUESTED            => 'Requested',
        self::STATUS_PENDING_REVIEW      => 'Pending Review',
        self::STATUS_AWAITING_EVIDENCE   => 'Awaiting Customer Evidence',
        self::STATUS_APPROVED            => 'Approved',
        self::STATUS_REJECTED            => 'Rejected',
        self::STATUS_AWAITING_SHIPMENT   => 'Awaiting Shipment',
        self::STATUS_IN_TRANSIT          => 'In Transit',
        self::STATUS_RECEIVED            => 'Item Received',
        self::STATUS_INSPECTION          => 'Under Inspection',
        self::STATUS_REFUND_APPROVED     => 'Refund Approved',
        self::STATUS_REFUND_PROCESSING   => 'Refund Processing',
        self::STATUS_REFUNDED            => 'Refund Completed',
        self::STATUS_REFUND_FAILED       => 'Refund Failed',
        self::STATUS_REPLACEMENT_APPROVED => 'Replacement Approved',
        self::STATUS_REPLACEMENT_SHIPPED => 'Replacement Shipped',
        self::STATUS_REPLACEMENT_DELIVERED => 'Replacement Delivered',
        self::STATUS_COMPLETED           => 'Completed',
        self::STATUS_CANCELLED           => 'Cancelled',
        self::STATUS_RETURN_COLLECTED    => 'Return Collected by Station',
    ];

    // ── Return Reasons ────────────────────────────────────────────────────────
    public const REASONS = [
        'wrong_item'           => 'Wrong item received',
        'wrong_size'           => 'Wrong size',
        'wrong_color'          => 'Wrong color',
        'damaged'              => 'Item arrived damaged',
        'missing_item'         => 'Missing item',
        'incomplete_order'     => 'Incomplete order',
        'not_as_described'     => 'Product not as described',
        'changed_mind'         => 'Changed my mind',
        'order_cancelled'      => 'Order cancelled',
    ];

    /**
     * Evidence requirements per reason.
     * photos: 'required', 'optional', 'no'
     * video:  'required', 'recommended', 'optional', 'no'
     * comments: 'required', 'optional', 'no'
     */
    public const EVIDENCE_RULES = [
        'wrong_item'           => ['photos' => 'required',  'video' => 'optional',  'comments' => 'required'],
        'wrong_size'           => ['photos' => 'required',  'video' => 'optional',  'comments' => 'required'],
        'wrong_color'          => ['photos' => 'required',  'video' => 'optional',  'comments' => 'required'],
        'damaged'              => ['photos' => 'required',  'video' => 'recommended', 'comments' => 'required'],
        'missing_item'         => ['photos' => 'optional',  'video' => 'no',        'comments' => 'required'],
        'incomplete_order'     => ['photos' => 'required',  'video' => 'optional',  'comments' => 'required'],
        'not_as_described'     => ['photos' => 'required',  'video' => 'recommended', 'comments' => 'required'],
        'changed_mind'         => ['photos' => 'no',        'video' => 'no',        'comments' => 'required'],
    ];

    // Reasons that qualify for shipping fee refund
    public const SHIPPING_REFUND_REASONS = [
        'wrong_item', 'wrong_size', 'wrong_color', 'damaged',
        'missing_item', 'incomplete_order', 'not_as_described',
    ];

    // ── Returnable Statuses (customer can still interact) ─────────────────────
    public const ACTIVE_STATUSES = [
        self::STATUS_REQUESTED,
        self::STATUS_PENDING_REVIEW,
        self::STATUS_AWAITING_EVIDENCE,
        self::STATUS_APPROVED,
        self::STATUS_AWAITING_SHIPMENT,
        self::STATUS_RETURN_COLLECTED,
    ];

    protected $fillable = [
        'order_id', 'order_item_id', 'exchange_variant_id', 'pickup_station_id', 'quantity', 'amount',
        'status', 'reason', 'details', 'evidence_path', 'evidence_video_path',
        'admin_note', 'reviewed_by', 'reviewed_at',
        'review_deadline', 'review_sla_breached',
        'opay_refund_no', 'opay_payload', 'payment_provider', 'provider_refund_reference',
        'refund_processing_at', 'last_refund_check_at',
        'inspection_notes', 'inspected_by', 'inspected_at',
        'inspection_deadline', 'inspection_sla_breached',
        'return_collected_at',
        'dropoff_deadline', 'dropoff_sla_breached',
    ];

    protected $casts = [
        'amount'               => 'decimal:2',
        'quantity'             => 'integer',
        'reviewed_at'          => 'datetime',
        'review_deadline'      => 'datetime',
        'review_sla_breached'  => 'boolean',
        'inspected_at'         => 'datetime',
        'inspection_deadline'  => 'datetime',
        'inspection_sla_breached' => 'boolean',
        'return_collected_at'  => 'datetime',
        'dropoff_deadline'     => 'datetime',
        'dropoff_sla_breached' => 'boolean',
        'refund_processing_at' => 'datetime',
        'last_refund_check_at' => 'datetime',
        'opay_payload'         => 'array',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function exchangeVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'exchange_variant_id');
    }

    public function pickupStation(): BelongsTo
    {
        return $this->belongsTo(PickupStation::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(ReturnAuditLog::class)->latest();
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function getReasonLabelAttribute(): string
    {
        return self::REASONS[$this->reason] ?? ucfirst(str_replace('_', ' ', $this->reason));
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst(str_replace('_', ' ', $this->status));
    }

    public function getEvidenceUrlAttribute(): ?string
    {
        return $this->evidence_path
            ? route('admin.refunds.evidence', $this)
            : null;
    }

    public function getEvidenceVideoUrlAttribute(): ?string
    {
        return $this->evidence_video_path
            ? route('admin.refunds.evidence-video', $this)
            : null;
    }

    public function getScopeLabel(): string
    {
        if ($this->orderItem) {
            $item = $this->orderItem;
            return ($item->product?->name ?? 'Item') .
                   ($item->variant?->options_label ? ' - ' . $item->variant->options_label : '') .
                   ' (×' . $this->quantity . ')';
        }
        return 'Full Order';
    }

    /**
     * Get evidence requirements for the current reason.
     */
    public function getEvidenceRequirements(): array
    {
        return self::EVIDENCE_RULES[$this->reason] ?? ['photos' => 'optional', 'video' => 'no', 'comments' => 'optional'];
    }

    /**
     * Get the return time limit in hours for the current reason.
     */
    public function getTimeLimitHours(): int
    {
        return self::REASON_TIME_LIMITS[$this->reason] ?? (self::REFUND_WINDOW_DAYS * 24);
    }

    /**
     * Check if the return request was submitted within the allowed time limit.
     * The limit is calculated from the order's delivery date (updated_at).
     */
    public function isWithinTimeLimit(): bool
    {
        $hours = $this->getTimeLimitHours();
        return $this->order->updated_at->diffInHours(now()) <= $hours;
    }

    /**
     * Check if this is an exchange request (customer wants a different variant).
     */
    public function isExchange(): bool
    {
        return $this->exchange_variant_id !== null;
    }

    /**
     * Get a human-readable label for the exchange variant.
     */
    public function getExchangeVariantLabel(): ?string
    {
        if (! $this->exchangeVariant) {
            return null;
        }

        $variant = $this->exchangeVariant;
        $label = $variant->options_label;

        if ($variant->selling_price !== null) {
            $label .= ' - ₦' . number_format($variant->net_price, 2);
        }

        return $label;
    }

    // ── Status Checks ─────────────────────────────────────────────────────────

    public function isPending(): bool
    {
        return in_array($this->status, [self::STATUS_REQUESTED, self::STATUS_PENDING_REVIEW], true);
    }

    public function isAwaitingEvidence(): bool
    {
        return $this->status === self::STATUS_AWAITING_EVIDENCE;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRefunded(): bool
    {
        return $this->status === self::STATUS_REFUNDED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, [
            self::STATUS_REFUNDED,
            self::STATUS_REPLACEMENT_DELIVERED,
            self::STATUS_COMPLETED,
        ], true);
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isReturnCollected(): bool
    {
        return $this->status === self::STATUS_RETURN_COLLECTED;
    }
}
