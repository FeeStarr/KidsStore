<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
class ProductVariant extends Model
{
    protected $fillable = [
        'product_id', 'sku', 'age_range_id', 'size_id', 'color_id', 'name', 'options',
        'selling_price', 'discount', 'image_id', 'is_active',
    ];

    protected $casts = [
        'options'       => 'array',
        'selling_price' => 'decimal:2',
        'discount'      => 'decimal:2',
        'is_active'     => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function ageRange(): BelongsTo
    {
        return $this->belongsTo(AgeRange::class, 'age_range_id');
    }

    public function sizeRef(): BelongsTo
    {
        return $this->belongsTo(Size::class, 'size_id');
    }

    public function colorRef(): BelongsTo
    {
        return $this->belongsTo(Color::class, 'color_id');
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(ProductImage::class, 'image_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'product_variant_id')->orderBy('sort_order');
    }

    public function inventory(): HasOne
    {
        return $this->hasOne(Inventory::class, 'product_variant_id');
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'product_variant_id');
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class, 'product_variant_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'product_variant_id');
    }

    public function deals(): BelongsToMany
    {
        return $this->belongsToMany(Deal::class, 'deal_variants')
            ->withTimestamps();
    }

    /**
     * "Pink / Large / 5-6 years" — built from FK relations only.
     */
    public function getOptionsLabelAttribute(): string
    {
        $parts = [];

        $colorName = $this->colorRef?->name;
        if ($colorName) {
            $parts[] = $colorName;
        }

        if (is_array($this->options) && ! empty($this->options)) {
            foreach ($this->options as $key => $value) {
                if ($value !== null && $value !== '') {
                    $parts[] = $value;
                }
            }
        }

        $sizeName = $this->sizeRef?->name;
        if ($sizeName && ! in_array($sizeName, $parts, true)) {
            $parts[] = $sizeName;
        }

        $ageName = $this->ageRange?->name;
        if ($ageName && ! in_array($ageName, $parts, true)) {
            $parts[] = $ageName;
        }

        return ! empty($parts)
            ? implode(' / ', $parts)
            : ($this->name ?: 'Default');
    }

    public function getEffectiveDiscountAttribute(): float
    {
        $productDiscount = (float) ($this->product?->discount ?? 0);
        $variantDiscount = (float) ($this->discount ?? 0);
        return min(100, max(0, $productDiscount + $variantDiscount));
    }

    public function getNetPriceAttribute(): float
    {
        return (float) $this->selling_price * (1 - ($this->effective_discount / 100));
    }

    public function getStockQuantityAttribute(): int
    {
        return (int) ($this->inventory?->quantity ?? 0);
    }

    /**
     * Display label combining product name + variant attributes.
     */
    public function getDisplayLabelAttribute(): string
    {
        $base = $this->product?->name ?: 'Product';
        $attr = $this->options_label;
        return $attr === 'Default' ? $base : "{$base} — {$attr}";
    }

    /**
     * Shorthand for variant grouping/thumbnails: returns the color as the visual grouping key.
     * (Useful for shop pages where you group variant thumbnails by color.)
     */
    public function getGroupingKeyAttribute(): string
    {
        return $this->colorRef?->name ?: 'Unspecified';
    }

    /**
     * Get the total stock across all sizes for this variant.
     */
    public function getTotalStockAttribute(): int
    {
        return (int) ($this->inventory?->quantity ?? 0);
    }
}
