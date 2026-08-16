<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use RuntimeException;

class CustomOrder extends Model
{
    use HasFactory;

    // Statuses
    const STATUS_DRAFT = 'draft';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_UNDER_REVIEW = 'under_review';
    const STATUS_NEEDS_INFORMATION = 'needs_information';
    const STATUS_QUOTE_PENDING = 'quote_pending';
    const STATUS_QUOTED = 'quoted';
    const STATUS_NEEDS_REVISION = 'needs_revision';
    const STATUS_CUSTOMER_APPROVED = 'customer_approved';
    const STATUS_PAYMENT_PENDING = 'payment_pending';
    const STATUS_PAID = 'paid';
    const STATUS_PRODUCTION_PENDING = 'production_pending';
    const STATUS_IN_PRODUCTION = 'in_production';
    const STATUS_QUALITY_CHECK = 'quality_check';
    const STATUS_REWORK_REQUIRED = 'rework_required';
    const STATUS_READY_FOR_DELIVERY = 'ready_for_delivery';
    const STATUS_SHIPPED = 'shipped';
    const STATUS_READY_FOR_PICKUP = 'ready_for_pickup';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REJECTED = 'rejected';
    const STATUS_QUOTE_EXPIRED = 'quote_expired';

    const VALID_TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_SUBMITTED],
        self::STATUS_SUBMITTED => [self::STATUS_UNDER_REVIEW, self::STATUS_REJECTED],
        self::STATUS_UNDER_REVIEW => [self::STATUS_NEEDS_INFORMATION, self::STATUS_QUOTE_PENDING, self::STATUS_REJECTED],
        self::STATUS_NEEDS_INFORMATION => [self::STATUS_UNDER_REVIEW, self::STATUS_CANCELLED],
        self::STATUS_QUOTE_PENDING => [self::STATUS_QUOTED],
        self::STATUS_QUOTED => [self::STATUS_CUSTOMER_APPROVED, self::STATUS_NEEDS_REVISION, self::STATUS_QUOTE_EXPIRED, self::STATUS_CANCELLED],
        self::STATUS_NEEDS_REVISION => [self::STATUS_QUOTE_PENDING],
        self::STATUS_CUSTOMER_APPROVED => [self::STATUS_PAYMENT_PENDING],
        self::STATUS_PAYMENT_PENDING => [self::STATUS_PAID, self::STATUS_CANCELLED],
        self::STATUS_PAID => [self::STATUS_PRODUCTION_PENDING],
        self::STATUS_PRODUCTION_PENDING => [self::STATUS_IN_PRODUCTION],
        self::STATUS_IN_PRODUCTION => [self::STATUS_QUALITY_CHECK],
        self::STATUS_QUALITY_CHECK => [self::STATUS_READY_FOR_DELIVERY, self::STATUS_READY_FOR_PICKUP, self::STATUS_REWORK_REQUIRED],
        self::STATUS_REWORK_REQUIRED => [self::STATUS_IN_PRODUCTION],
        self::STATUS_READY_FOR_DELIVERY => [self::STATUS_SHIPPED],
        self::STATUS_SHIPPED => [self::STATUS_COMPLETED],
        self::STATUS_READY_FOR_PICKUP => [self::STATUS_COMPLETED],
    ];

    const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_SUBMITTED => 'Submitted',
        self::STATUS_UNDER_REVIEW => 'Under Review',
        self::STATUS_NEEDS_INFORMATION => 'Needs Information',
        self::STATUS_QUOTE_PENDING => 'Quote Pending',
        self::STATUS_QUOTED => 'Quoted',
        self::STATUS_NEEDS_REVISION => 'Needs Revision',
        self::STATUS_CUSTOMER_APPROVED => 'Customer Approved',
        self::STATUS_PAYMENT_PENDING => 'Payment Pending',
        self::STATUS_PAID => 'Paid',
        self::STATUS_PRODUCTION_PENDING => 'Production Pending',
        self::STATUS_IN_PRODUCTION => 'In Production',
        self::STATUS_QUALITY_CHECK => 'Quality Check',
        self::STATUS_REWORK_REQUIRED => 'Rework Required',
        self::STATUS_READY_FOR_DELIVERY => 'Ready for Delivery',
        self::STATUS_SHIPPED => 'Shipped',
        self::STATUS_READY_FOR_PICKUP => 'Ready for Pickup',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_CANCELLED => 'Cancelled',
        self::STATUS_REJECTED => 'Rejected',
        self::STATUS_QUOTE_EXPIRED => 'Quote Expired',
    ];

    protected $fillable = [
        'custom_order_number', 'user_id', 'item_type', 'status', 'payment_status',
        'base_product_id', 'child_name', 'child_age', 'child_gender',
        'delivery_method', 'pickup_station_id', 'delivery_address',
        'customer_notes', 'admin_notes', 'production_notes', 'customer_visible_notes',
        'custom_colour_description',
        'subtotal', 'customization_fee', 'rush_fee', 'delivery_fee', 'total_amount', 'amount_paid',
        'quote_valid_until', 'estimated_production_days', 'return_policy_acknowledged',
        'tracking_number', 'courier_name',
        'submitted_at', 'quoted_at', 'approved_at', 'paid_at',
        'production_started_at', 'completed_at', 'cancelled_at',
    ];

    protected $casts = [
        'child_age' => 'integer',
        'subtotal' => 'decimal:2',
        'customization_fee' => 'decimal:2',
        'rush_fee' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'quote_valid_until' => 'datetime',
        'estimated_production_days' => 'integer',
        'return_policy_acknowledged' => 'boolean',
        'submitted_at' => 'datetime',
        'quoted_at' => 'datetime',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'production_started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected $appends = ['status_label'];

    // ── Relationships ──────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function baseProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'base_product_id');
    }

    public function pickupStation(): BelongsTo
    {
        return $this->belongsTo(PickupStation::class);
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(CustomOrderMeasurement::class);
    }

    public function customizations(): HasMany
    {
        return $this->hasMany(CustomOrderCustomization::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(CustomOrderFile::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(CustomOrderQuote::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CustomOrderMessage::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(CustomOrderStatusHistory::class);
    }

    public function qcChecks(): HasMany
    {
        return $this->hasMany(CustomOrderQcCheck::class);
    }

    public function order(): HasOne
    {
        return $this->hasOne(Order::class);
    }

    // ── Accessors ──────────────────────────────────────────────────

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst(str_replace('_', ' ', $this->status));
    }

    // ── Methods ────────────────────────────────────────────────────

    public static function generateReference(): string
    {
        $last = static::orderByDesc('id')->value('custom_order_number');
        $next = 1;

        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $next = (int) $m[1] + 1;
        }

        return 'CF-' . str_pad($next, 6, '0', STR_PAD_LEFT);
    }

    public static function canTransition(string $current, string $new): bool
    {
        return in_array($new, self::VALID_TRANSITIONS[$current] ?? [], true);
    }

    public function latestQuote(): ?CustomOrderQuote
    {
        return $this->quotes()->latest('version')->first();
    }

    public function approvedQuote(): ?CustomOrderQuote
    {
        return $this->quotes()->where('status', 'approved')->latest('version')->first();
    }

    public function referenceFiles()
    {
        return $this->files()->where('file_type', 'reference_image');
    }

    public function getCustomizationValue(string $attribute): ?string
    {
        return $this->customizations()->where('attribute', $attribute)->value('value');
    }
}
