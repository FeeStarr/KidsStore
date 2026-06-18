<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id', 'brand_id', 'sku', 'name', 'slug', 'description', 'image', 'status',
        'age_group', 'gender', 'brand',
        'selling_price', 'discount', 'is_active',
    ];

    protected $casts = [
        'selling_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'is_active' => 'boolean',
        'age_group' => 'array',
    ];

    protected $appends = [
        'catalog_image',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brandRef(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    public function inventory(): HasOne
    {
        return $this->hasOne(Inventory::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('id');
    }

    /**
     * The first/auto-created variant. Every product has at least one ("Default").
     */
    public function defaultVariant(): HasOne
    {
        return $this->hasOne(ProductVariant::class)->oldestOfMany('id');
    }

    public function hasMultipleVariants(): bool
    {
        return $this->variants()->count() > 1;
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class)->latest();
    }

    public function getAverageRatingAttribute(): float
    {
        return (float) ($this->reviews()->avg('rating') ?? 0);
    }

    public function getReviewsCountAttribute(): int
    {
        return (int) $this->reviews()->count();
    }

    /**
     * Net selling price after applying the percentage discount.
     */
    public function getNetPriceAttribute(): float
    {
        return (float) $this->selling_price * (1 - ((float) $this->discount / 100));
    }

    public function getStockQuantityAttribute(): int
    {
        // With variants, total stock = sum of all variant inventories.
        return (int) $this->variants()
            ->withSum('inventory as variant_qty', 'quantity')
            ->get()
            ->sum('variant_qty');
    }

    /**
     * Lowest active variant selling price (after discount), used for storefront cards.
     */
    public function getPriceFromAttribute(): float
    {
        $productDiscount = (float) ($this->discount ?? 0);
        $min = $this->variants()->where('is_active', true)
            ->get()
            ->map(fn ($v) => (float) $v->selling_price * (1 - min(100, max(0, $productDiscount + (float) $v->discount)) / 100))
            ->filter(fn ($p) => $p > 0)
            ->min();
        return (float) ($min ?? 0);
    }

    public function getPriceToAttribute(): float
    {
        $productDiscount = (float) ($this->discount ?? 0);
        $max = $this->variants()->where('is_active', true)
            ->get()
            ->map(fn ($v) => (float) $v->selling_price * (1 - min(100, max(0, $productDiscount + (float) $v->discount)) / 100))
            ->max();
        return (float) ($max ?? 0);
    }

    /**
     * Get all distinct colors available across active variants.
     */
    public function getAvailableColorsAttribute(): array
    {
        return $this->variants()
            ->where('is_active', true)
            ->whereNotNull('color')
            ->distinct()
            ->pluck('color')
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Get all distinct sizes available across active variants via the size FK.
     */
    public function getAvailableSizesAttribute(): array
    {
        return $this->variants()
            ->where('is_active', true)
            ->whereNotNull('size_id')
            ->with('sizeRef')
            ->get()
            ->pluck('sizeRef.name')
            ->filter(fn ($s) => $s !== null && $s !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function getCatalogImageAttribute(): ?string
    {
        return $this->image ?: $this->primaryImage?->url;
    }
}
