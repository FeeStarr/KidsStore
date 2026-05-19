<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id', 'sku', 'name', 'options',
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

    /**
     * "Pink / 3-4" — built from the options JSON, or the stored name as fallback.
     */
    public function getOptionsLabelAttribute(): string
    {
        if (! is_array($this->options) || empty($this->options)) {
            return $this->name ?: 'Default';
        }

        return collect($this->options)
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->values()
            ->implode(' / ');
    }

    public function getNetPriceAttribute(): float
    {
        return (float) $this->selling_price * (1 - ((float) $this->discount / 100));
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
}
