<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Deal extends Model
{
    use SoftDeletes;

    public const TYPE_PERCENTAGE  = 'percentage';
    public const TYPE_FIXED_AMOUNT = 'fixed_amount';
    public const TYPE_FIXED_PRICE  = 'fixed_price';

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_EXPIRED   = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'title', 'slug', 'description',
        'discount_type', 'discount_value',
        'starts_at', 'ends_at',
        'status', 'banner_image', 'thumbnail_image',
        'is_featured', 'max_uses', 'current_uses', 'created_by',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'starts_at'      => 'datetime',
        'ends_at'        => 'datetime',
        'is_featured'    => 'boolean',
        'max_uses'       => 'integer',
        'current_uses'   => 'integer',
    ];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'deal_products')
            ->withTimestamps();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Active deals right now (time window + not draft/cancelled).
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query
            ->whereNotIn('status', [self::STATUS_DRAFT, self::STATUS_CANCELLED])
            ->where('starts_at', '<=', now())
            ->where(function (Builder $q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            });
    }

    /**
     * Deals that have started (regardless of whether they've ended).
     */
    public function scopeStarted(Builder $query): Builder
    {
        return $query->whereNotIn('status', [self::STATUS_DRAFT, self::STATUS_CANCELLED]);
    }

    public function getStatusLabelAttribute(): string
    {
        return ucfirst(str_replace('_', ' ', $this->status));
    }

    public function getDiscountLabelAttribute(): string
    {
        $value = rtrim(rtrim(number_format((float) $this->discount_value, 2), '0'), '.');

        return match ($this->discount_type) {
            self::TYPE_PERCENTAGE  => "{$value}% off",
            self::TYPE_FIXED_AMOUNT => "₦{$value} off",
            self::TYPE_FIXED_PRICE  => "₦{$value}",
            default => $value,
        };
    }

    public function getBadgeTextAttribute(): string
    {
        $value = rtrim(rtrim(number_format((float) $this->discount_value, 2), '0'), '.');

        return match ($this->discount_type) {
            self::TYPE_PERCENTAGE  => "{$value}% OFF",
            self::TYPE_FIXED_AMOUNT => "₦{$value} OFF",
            self::TYPE_FIXED_PRICE  => "₦{$value}",
            default => 'SALE',
        };
    }

    public function getIsLiveAttribute(): bool
    {
        return $this->starts_at !== null
            && $this->starts_at <= now()
            && ($this->ends_at === null || $this->ends_at > now())
            && ! in_array($this->status, [self::STATUS_DRAFT, self::STATUS_CANCELLED], true);
    }

    /**
     * Compute the deal price from an original (base) price.
     * Never below zero, always server-side.
     */
    public function priceFor(float $originalPrice): float
    {
        $original = max(0, $originalPrice);
        $value    = (float) $this->discount_value;

        $price = match ($this->discount_type) {
            self::TYPE_PERCENTAGE  => $original * (1 - min(100, max(0, $value)) / 100),
            self::TYPE_FIXED_AMOUNT => $original - max(0, $value),
            self::TYPE_FIXED_PRICE  => max(0, $value),
            default => $original,
        };

        return round(max(0, $price), 2);
    }

    /**
     * Monetary discount per unit for a given original price.
     */
    public function discountFor(float $originalPrice): float
    {
        return round(max(0, $originalPrice - $this->priceFor($originalPrice)), 2);
    }

    /**
     * The "effective" status resolved from the clock, used when syncing rows
     * and when displaying a deal whose stored status may be stale.
     */
    public function computedStatus(): string
    {
        if (in_array($this->status, [self::STATUS_DRAFT, self::STATUS_CANCELLED], true)) {
            return $this->status;
        }

        $now = now();
        if ($this->starts_at !== null && $this->starts_at > $now) {
            return self::STATUS_SCHEDULED;
        }
        if ($this->ends_at !== null && $this->ends_at <= $now) {
            return self::STATUS_EXPIRED;
        }

        return self::STATUS_ACTIVE;
    }

    /**
     * Refresh the stored status column from the clock.
     */
    public function syncStatus(): self
    {
        $computed = $this->computedStatus();
        if ($this->status !== $computed) {
            $this->updateQuietly(['status' => $computed]);
        }

        return $this;
    }
}
