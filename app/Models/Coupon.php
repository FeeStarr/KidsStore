<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Coupon extends Model
{
    use SoftDeletes;

    public const TYPE_PERCENTAGE   = 'percentage';
    public const TYPE_FIXED_AMOUNT = 'fixed_amount';
    public const TYPE_FIXED_PRICE  = 'fixed_price';

    public const APPLIES_ALL              = 'all';
    public const APPLIES_REGULAR_PRICE_ONLY = 'regular_price_only';

    public const STATUS_ACTIVE   = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'code', 'name', 'description',
        'discount_type', 'discount_value',
        'applies_to', 'minimum_order_amount', 'maximum_discount_amount',
        'starts_at', 'ends_at',
        'status', 'usage_limit', 'usage_count', 'per_customer_limit', 'created_by',
    ];

    protected $casts = [
        'discount_value'        => 'decimal:2',
        'minimum_order_amount'  => 'decimal:2',
        'maximum_discount_amount' => 'decimal:2',
        'starts_at'             => 'datetime',
        'ends_at'               => 'datetime',
        'usage_limit'           => 'integer',
        'usage_count'           => 'integer',
        'per_customer_limit'    => 'integer',
    ];

    protected static function booted(): void
    {
        // Normalize codes to lowercase before save/lookup so matching is case-insensitive.
        static::saving(function (self $coupon) {
            $coupon->code = strtolower(trim($coupon->code));
        });
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'coupon_products')
            ->withTimestamps();
    }

    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(ProductVariant::class, 'coupon_variants')
            ->withTimestamps();
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && ($this->starts_at === null || $this->starts_at <= now())
            && ($this->ends_at === null || $this->ends_at > now());
    }

    public function getDiscountLabelAttribute(): string
    {
        $value = rtrim(rtrim(number_format((float) $this->discount_value, 2), '0'), '.');

        return match ($this->discount_type) {
            self::TYPE_PERCENTAGE   => "{$value}% off",
            self::TYPE_FIXED_AMOUNT => "₦{$value} off",
            self::TYPE_FIXED_PRICE  => "₦{$value}",
            default => $value,
        };
    }

    /**
     * Compute the coupon discount value from an original price.
     * Percentage/fixed-amount apply to the price; fixed-price sets an absolute price.
     */
    public function priceFor(float $originalPrice): float
    {
        $original = max(0, $originalPrice);
        $value    = (float) $this->discount_value;

        $price = match ($this->discount_type) {
            self::TYPE_PERCENTAGE    => $original * (1 - min(100, max(0, $value)) / 100),
            self::TYPE_FIXED_AMOUNT  => $original - max(0, $value),
            self::TYPE_FIXED_PRICE   => max(0, $value),
            default => $original,
        };

        return round(max(0, $price), 2);
    }

    public function discountFor(float $originalPrice): float
    {
        return round(max(0, $originalPrice - $this->priceFor($originalPrice)), 2);
    }
}