<?php

namespace App\Models;

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
        self::STATUS_SUBMITTED => [self::STATUS_UNDER_REVIEW, self::STATUS_NEEDS_INFORMATION, self::STATUS_QUOTED, self::STATUS_REJECTED, self::STATUS_CANCELLED],
        self::STATUS_UNDER_REVIEW => [self::STATUS_NEEDS_INFORMATION, self::STATUS_QUOTE_PENDING, self::STATUS_REJECTED, self::STATUS_CANCELLED],
        self::STATUS_NEEDS_INFORMATION => [self::STATUS_UNDER_REVIEW, self::STATUS_CANCELLED],
        self::STATUS_QUOTE_PENDING => [self::STATUS_QUOTED, self::STATUS_CANCELLED],
        self::STATUS_QUOTED => [self::STATUS_CUSTOMER_APPROVED, self::STATUS_NEEDS_REVISION, self::STATUS_QUOTE_EXPIRED, self::STATUS_CANCELLED],
        self::STATUS_NEEDS_REVISION => [self::STATUS_QUOTE_PENDING, self::STATUS_QUOTED, self::STATUS_CANCELLED],
        self::STATUS_CUSTOMER_APPROVED => [self::STATUS_PAYMENT_PENDING, self::STATUS_CANCELLED],
        self::STATUS_PAYMENT_PENDING => [self::STATUS_PAID, self::STATUS_CANCELLED],
        self::STATUS_PAID => [self::STATUS_PRODUCTION_PENDING, self::STATUS_CANCELLED],
        self::STATUS_PRODUCTION_PENDING => [self::STATUS_IN_PRODUCTION, self::STATUS_CANCELLED],
        self::STATUS_IN_PRODUCTION => [self::STATUS_QUALITY_CHECK, self::STATUS_CANCELLED],
        self::STATUS_QUALITY_CHECK => [self::STATUS_READY_FOR_DELIVERY, self::STATUS_READY_FOR_PICKUP, self::STATUS_REWORK_REQUIRED, self::STATUS_CANCELLED],
        self::STATUS_REWORK_REQUIRED => [self::STATUS_IN_PRODUCTION, self::STATUS_CANCELLED],
        self::STATUS_READY_FOR_DELIVERY => [self::STATUS_SHIPPED, self::STATUS_CANCELLED],
        self::STATUS_SHIPPED => [self::STATUS_COMPLETED],
        self::STATUS_READY_FOR_PICKUP => [self::STATUS_COMPLETED, self::STATUS_CANCELLED],
    ];

    /**
     * The statuses a customer may cancel from.
     *
     * A customer can walk away at any point before they have paid. Once they have
     * paid it is not a cancellation any more, it is a refund, and that is a separate
     * request the customer raises rather than a decision the system makes.
     */
    const CUSTOMER_CANCELLABLE_STATUSES = [
        self::STATUS_SUBMITTED,
        self::STATUS_UNDER_REVIEW,
        self::STATUS_NEEDS_INFORMATION,
        self::STATUS_QUOTE_PENDING,
        self::STATUS_QUOTED,
        self::STATUS_NEEDS_REVISION,
        self::STATUS_CUSTOMER_APPROVED,
        self::STATUS_PAYMENT_PENDING,
    ];

    /**
     * The statuses an administrator may cancel from.
     *
     * Wider than the customer's, because an administrator can scrap a frock that has
     * been paid for or is part way through being made. Past shipping a frock is
     * returned rather than cancelled, so that is not on the list.
     */
    const ADMIN_CANCELLABLE_STATUSES = [
        self::STATUS_SUBMITTED,
        self::STATUS_UNDER_REVIEW,
        self::STATUS_NEEDS_INFORMATION,
        self::STATUS_QUOTE_PENDING,
        self::STATUS_QUOTED,
        self::STATUS_NEEDS_REVISION,
        self::STATUS_CUSTOMER_APPROVED,
        self::STATUS_PAYMENT_PENDING,
        self::STATUS_PAID,
        self::STATUS_PRODUCTION_PENDING,
        self::STATUS_IN_PRODUCTION,
        self::STATUS_QUALITY_CHECK,
        self::STATUS_REWORK_REQUIRED,
        self::STATUS_READY_FOR_DELIVERY,
        self::STATUS_READY_FOR_PICKUP,
    ];

    /**
     * Whether a customer may cancel this request right now.
     */
    public function isCancellableByCustomer(): bool
    {
        return in_array($this->status, self::CUSTOMER_CANCELLABLE_STATUSES, true);
    }

    /**
     * Whether an administrator may cancel this request right now.
     */
    public function isCancellableByAdmin(): bool
    {
        return in_array($this->status, self::ADMIN_CANCELLABLE_STATUSES, true);
    }

    /**
     * Statuses the record only reaches after the customer approves a quote and a
     * payable linked order is created. See lifecycle_label for how this is used.
     */
    const POST_APPROVAL_STATUSES = [
        self::STATUS_CUSTOMER_APPROVED,
        self::STATUS_PAYMENT_PENDING,
        self::STATUS_PAID,
        self::STATUS_PRODUCTION_PENDING,
        self::STATUS_IN_PRODUCTION,
        self::STATUS_QUALITY_CHECK,
        self::STATUS_REWORK_REQUIRED,
        self::STATUS_READY_FOR_DELIVERY,
        self::STATUS_READY_FOR_PICKUP,
        self::STATUS_SHIPPED,
        self::STATUS_COMPLETED,
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

    const CUSTOMER_STATUS_LABELS = [
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
        self::STATUS_IN_PRODUCTION => 'Being Made',
        self::STATUS_QUALITY_CHECK => 'Quality Check in Progress',
        self::STATUS_REWORK_REQUIRED => 'Being Finalized',
        self::STATUS_READY_FOR_DELIVERY => 'Ready for Delivery',
        self::STATUS_SHIPPED => 'Shipped',
        self::STATUS_READY_FOR_PICKUP => 'Ready for Pickup',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_CANCELLED => 'Cancelled',
        self::STATUS_REJECTED => 'Rejected',
        self::STATUS_QUOTE_EXPIRED => 'Quote Expired',
    ];

    const CATEGORIES = [
        'birthday' => 'Birthday Dresses',
        'princess' => 'Princess Dresses',
        'ankara' => 'Ankara',
        'special_occasion' => 'Special Occasion',
    ];

    protected $fillable = [
        'custom_order_number', 'submission_token', 'user_id', 'item_type', 'category', 'status', 'payment_status',
        'base_product_id', 'custom_creation_id', 'requires_measurement_confirmation',
        'sizing_method', 'size_chart_id', 'size_chart_version', 'selected_chart_size', 'selected_chart_fit',
        'sizing_snapshot', 'sizing_confirmed_at', 'sizing_policy_acknowledged_at',
        'return_policy_acknowledged_at', 'child_name', 'child_age', 'child_gender',
        'delivery_method', 'pickup_station_id', 'delivery_location_id', 'delivery_address',
        'customer_notes', 'admin_notes', 'production_notes', 'customer_visible_notes',
        'custom_colour_description',
        'subtotal', 'customization_fee', 'rush_fee', 'delivery_fee', 'total_amount', 'amount_paid',
        'quote_valid_until', 'estimated_production_days', 'return_policy_acknowledged',
        'tracking_number', 'courier_name',
        'submitted_at', 'quoted_at', 'approved_at', 'paid_at',
        'production_started_at', 'completed_at', 'cancelled_at',
        'showcase_enabled', 'showcase_title', 'showcase_price',
        'showcase_description', 'showcase_image_path', 'showcase_category',
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
        'requires_measurement_confirmation' => 'boolean',
        'sizing_snapshot' => 'array',
        'sizing_confirmed_at' => 'datetime',
        'sizing_policy_acknowledged_at' => 'datetime',
        'return_policy_acknowledged_at' => 'datetime',
        'submitted_at' => 'datetime',
        'quoted_at' => 'datetime',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'production_started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'showcase_enabled' => 'boolean',
        'showcase_price' => 'decimal:2',
    ];

    public function getCategoryLabelAttribute(): ?string
    {
        if (! $this->category) {
            return null;
        }

        return self::CATEGORIES[$this->category] ?? $this->category;
    }

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

    /**
     * The design this request was made from, if it was started from one.
     */
    public function customCreation(): BelongsTo
    {
        return $this->belongsTo(CustomCreation::class, 'custom_creation_id');
    }

    public function pickupStation(): BelongsTo
    {
        return $this->belongsTo(PickupStation::class);
    }

    public function deliveryLocation(): BelongsTo
    {
        return $this->belongsTo(DeliveryLocation::class);
    }

    public function measurements(): HasMany
    {
        // Production measurements only. Every existing view and query keeps
        // meaning "what the tailor cuts to"; comparison figures have their own
        // relation so the two can never be mistaken for one another.
        return $this->hasMany(CustomOrderMeasurement::class)
            ->where('role', CustomOrderMeasurement::ROLE_PRODUCTION);
    }

    /**
     * Figures the parent supplied so they could be set against the standard
     * measurements. These are evidence for comparison, never a cutting basis.
     */
    public function comparisonMeasurements(): HasMany
    {
        return $this->hasMany(CustomOrderMeasurement::class)
            ->where('role', CustomOrderMeasurement::ROLE_COMPARISON);
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

    public function sizeChart(): BelongsTo
    {
        return $this->belongsTo(SizeChart::class, 'size_chart_id');
    }

    // ── Accessors ──────────────────────────────────────────────────

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst(str_replace('_', ' ', $this->status));
    }

    public function getCustomerStatusLabelAttribute(): string
    {
        return self::CUSTOMER_STATUS_LABELS[$this->status] ?? ucfirst(str_replace('_', ' ', $this->status));
    }

    /**
     * Whether a payable linked order actually exists for this record.
     */
    public function hasPayableOrder(): bool
    {
        return $this->order !== null;
    }

    /**
     * What this record is called, which depends on what the customer has committed to
     * rather than on whether they have paid yet. An unpaid order is still an order.
     *
     *   - "Custom Request" - an enquiry and the quote we sent back. Nothing is owed.
     *   - "Custom Order"  - a live, payable order (ORD-... exists).
     *
     * The linked order is created in the same transaction that approves the quote, so
     * its existence is the truthful signal. Deriving the label from it rather than from
     * the status alone means a record stranded in a post-approval status by the old
     * non-atomic flow cannot claim an order exists when none does - see
     * isStrandedAfterApproval().
     */
    public function getLifecycleLabelAttribute(): string
    {
        return $this->hasPayableOrder() ? 'Custom Order' : 'Custom Request';
    }

    /**
     * A record that reached a post-approval status but has no linked order - only
     * possible for rows stranded by the pre-atomic approval flow. The UI must not
     * claim an order exists; it prompts for a retry instead.
     */
    public function isStrandedAfterApproval(): bool
    {
        return in_array($this->status, self::POST_APPROVAL_STATUSES, true)
            && ! $this->hasPayableOrder();
    }

    // ── Methods ────────────────────────────────────────────────────

    public const SIZING_STANDARD = 'standard_size';
    public const SIZING_MEASUREMENTS = 'custom_measurements';

    /**
     * What is still missing before this order can be produced.
     *
     * Evaluated in full before anything is written, so a refusal leaves the order
     * exactly as it was.
     *
     * @return array<int, string>
     */
    public function productionGateBlockers(): array
    {
        $blockers = [];

        if (! $this->sizing_confirmed_at) {
            $blockers[] = 'The customer has not confirmed the sizing.';
        }

        if ($this->requires_measurement_confirmation
            && $this->comparisonMeasurements->isEmpty()) {
            $blockers[] = 'This design requires the child\'s current measurements for comparison, and none have been supplied.';
        }

        // The confirmed evidence must still describe the basis the order carries.
        // If the production measurements or the sizing method changed after the
        // customer agreed to them, the agreement no longer applies.
        if ($this->sizing_confirmed_at && ! $this->confirmationStillMatchesBasis()) {
            $blockers[] = 'The sizing basis changed after it was confirmed, so it must be confirmed again.';
        }

        if ($this->sizing_method === self::SIZING_MEASUREMENTS && $this->measurements->isEmpty()) {
            $blockers[] = 'The order says it is made to the customer\'s measurements, but none are recorded.';
        }

        if ($this->productionBasis() === null) {
            $blockers[] = 'No sizing basis has been chosen for this order.';
        }

        return $blockers;
    }

    /**
     * Whether the recorded confirmation still describes the sizing basis the order
     * actually carries.
     */
    public function confirmationStillMatchesBasis(): bool
    {
        $snapshot = $this->sizing_snapshot;

        if (! is_array($snapshot)) {
            return false;
        }

        if (($snapshot['production_basis'] ?? null) !== $this->productionBasis()) {
            return false;
        }

        $recorded = collect($snapshot['production_measurements'] ?? [])
            ->mapWithKeys(fn ($m) => [$m['type'] => (float) $m['value']]);

        $current = $this->measurements->mapWithKeys(fn ($m) => [
            $m->measurement_type => (float) $m->measurement_value,
        ]);

        return $recorded->all() == $current->all();
    }

    /**
     * Captures the sizing evidence exactly as it stood at confirmation.
     *
     * Freezing this is what makes a later sizing dispute resolvable: if the chart is
     * edited or a new version published, this order still describes what the customer
     * actually saw and agreed to.
     *
     * A snapshot is written once. Once the order has been confirmed, this method
     * refuses to touch it, so a later change to a chart, a guide or a design flag
     * cannot rewrite what was already agreed.
     */
    public function captureSizingSnapshot(?SizeChart $chart = null, ?string $comparisonSource = null, ?int $confirmedBy = null): void
    {
        // The evidence is written once. A confirmed order keeps the record it was
        // given, so a later change to a chart, a guide or a design flag cannot
        // rewrite what was already agreed.
        if ($this->sizing_snapshot) {
            return;
        }

        $chart ??= $this->sizeChart;

        $entry = $chart?->entries->firstWhere(
            fn ($e) => $e->label === $this->selected_chart_size
                && $e->fit === $this->selected_chart_fit
        );

        $this->sizing_snapshot = $this->buildSizingSnapshot($chart, $entry, $comparisonSource, $confirmedBy);
    }

    /**
     * The evidence, assembled.
     *
     * The production basis is recorded explicitly rather than inferred, so a
     * comparison figure can never be mistaken for the figure the frock is cut to.
     */
    private function buildSizingSnapshot(?SizeChart $chart, ?SizeChartEntry $entry, ?string $comparisonSource = null, ?int $confirmedBy = null): array
    {
        $standardFigures = $entry
            ? $entry->measurements->map(fn ($m) => [
                'type' => $m->measurement_type,
                'basis' => $m->basis,
                'min' => $m->min_value,
                'max' => $m->max_value,
                'unit' => $m->unit,
            ])->all()
            : null;

        return [
            'captured_at' => now()->toIso8601String(),
            'confirmation' => [
                'confirmed_at' => $this->sizing_confirmed_at?->toIso8601String(),
                'policy_acknowledged_at' => $this->sizing_policy_acknowledged_at?->toIso8601String(),
                'confirmed_by_user_id' => $confirmedBy ?? $this->user_id,
            ],
            'design' => [
                'creation_id' => $this->custom_creation_id,
                'title' => $this->customCreation?->title,
                'requires_measurement_confirmation' => (bool) $this->requires_measurement_confirmation,
            ],
            'chart' => [
                'id' => $chart?->id ?? $this->size_chart_id,
                'name' => $chart?->name,
                'version' => $chart?->version ?? $this->size_chart_version,
                'default_basis' => $chart?->default_basis,
            ],
            'selection' => [
                'sizing_method' => $this->sizing_method,
                'age_band' => $this->selected_chart_size,
                // Null means no tier was assigned; recording it as a value would
                // invent a choice nobody made.
                'fit' => $this->selected_chart_fit,
                'fit_label' => $this->selected_chart_fit !== null
                    ? (SizeChart::FIT_LABELS[$this->selected_chart_fit] ?? $this->selected_chart_fit)
                    : null,
                'combined_label' => $entry?->combinedLabel(),
            ],
            'standard_measurements' => $standardFigures,
            // The figures the frock is actually cut from. When the basis is the
            // customer's own measurements these are the customer's, and the chart
            // figures are only what was shown to them. When the basis is the
            // standard size, these are the chart figures for that size and fitting.
            'production_figures' => $this->productionBasis() === self::SIZING_MEASUREMENTS
                ? null
                : $standardFigures,
            'guides' => $this->captureGuideWording($standardFigures),
            'production_measurements' => $this->measurements->map(fn ($m) => [
                'type' => $m->measurement_type,
                'label' => CustomOrderMeasurementField::where('name', $m->measurement_type)->value('label')
                    ?? $m->measurement_type,
                'value' => (float) $m->measurement_value,
                'unit' => $m->measurement_unit,
            ])->all(),
            'comparison_measurements' => $this->comparisonMeasurements->map(fn ($m) => [
                'type' => $m->measurement_type,
                'label' => CustomOrderMeasurementField::where('name', $m->measurement_type)->value('label')
                    ?? $m->measurement_type,
                'value' => (float) $m->measurement_value,
                'unit' => $m->measurement_unit,
            ])->all(),
            // Whether the comparison figures were supplied fresh or were the
            // customer's own production figures, acknowledged as current. The values
            // may be identical; their provenance is what matters.
            'comparison_measurements_source' => $comparisonSource,
            // What the frock is actually cut to. Never the comparison figures.
            'production_basis' => $this->productionBasis(),
        ];
    }

    /**
     * The wording the customer was shown for each measurement type, with the
     * instruction text and whether that guide was active at the time.
     */
    private function captureGuideWording(?array $standardFigures): array
    {
        if (! $standardFigures) {
            return [];
        }

        $types = collect($standardFigures)->pluck('type')->unique();
        $bases = collect($standardFigures)->pluck('basis')->unique();

        return CustomOrderMeasurementGuide::query()
            ->whereIn('measurement_type', $types)
            ->whereIn('basis', $bases)
            ->get()
            ->map(fn ($g) => [
                'measurement_type' => $g->measurement_type,
                'basis' => $g->basis,
                'name' => $g->name,
                'description' => $g->description,
                'is_active' => (bool) $g->is_active,
            ])->values()->all();
    }

    /**
     * What the frock will be cut to.
     *
     * A parent's own measurements are the basis. Otherwise the standard size is.
     * Comparison figures never appear here.
     */
    public function productionBasis(): ?string
    {
        if ($this->sizing_method === self::SIZING_MEASUREMENTS && $this->measurements->isNotEmpty()) {
            return self::SIZING_MEASUREMENTS;
        }

        if ($this->selected_chart_size !== null) {
            return self::SIZING_STANDARD;
        }

        return null;
    }

    /**
     * Resize risk worth flagging to a parent. This is the reason sizing evidence is
     * captured at all: a growing child between ordering and wearing the frock is a
     * foreseeable outcome, not a defect.
     */
    public function sizingRiskNote(): ?string
    {
        if ($this->sizing_method !== self::SIZING_MEASUREMENTS) {
            return null;
        }

        if (! $this->sizing_confirmed_at) {
            return 'Sizing not confirmed yet.';
        }

        $weeks = (int) floor($this->sizing_confirmed_at->diffInWeeks(now()));

        if ($weeks < 1) {
            return null;
        }

        return sprintf(
            'Sizing confirmed %d week%s ago. Children grow quickly, so it is worth re-checking the measurements before the frock is cut.',
            $weeks,
            $weeks === 1 ? '' : 's'
        );
    }

    public static function generateReference(): string
    {
        $last = static::orderByDesc('id')->value('custom_order_number');
        $next = 1;

        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $next = (int) $m[1] + 1;
        }

        $reference = 'CF-' . str_pad($next, 6, '0', STR_PAD_LEFT);

        // Two concurrent submissions can read the same "last" reference, so step
        // forward until the candidate is actually free.
        while (static::where('custom_order_number', $reference)->exists()) {
            $next++;
            $reference = 'CF-' . str_pad($next, 6, '0', STR_PAD_LEFT);
        }

        return $reference;
    }

    /**
     * Find the order created by an earlier submission carrying the same token.
     */
    public static function findBySubmissionToken(?string $token, ?int $userId = null): ?self
    {
        if (blank($token)) {
            return null;
        }

        return static::where('submission_token', $token)
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->first();
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
