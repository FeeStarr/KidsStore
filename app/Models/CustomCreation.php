<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CustomCreation extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'image_path',
        'price',
        'is_price_from',
        'description',
        'category',
        'age_range',
        'product_id',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_price_from' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    const CATEGORIES = [];

    // ── Relations ───────────────────────────────────────────────

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // ── Scopes ──────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    // ── Accessors ───────────────────────────────────────────────

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image_path) {
            return null;
        }

        return Storage::disk('public')->url($this->image_path);
    }

    /**
     * Derived purchase state — never stored:
     *  - available:    linked product visible on the shop and in stock
     *  - out_of_stock: linked product visible on the shop but sold out
     *  - request_only: no linked product (or product no longer visible)
     */
    public function getAvailabilityAttribute(): string
    {
        $product = $this->product;

        if (!$product || !$this->productIsVisible($product)) {
            return 'request_only';
        }

        return (int) $product->stock_quantity > 0 ? 'available' : 'out_of_stock';
    }

    public function getIsAvailableAttribute(): bool
    {
        return $this->availability === 'available';
    }

    private function productIsVisible(Product $product): bool
    {
        return $product->status
            ? $product->status === 'active'
            : (bool) $product->is_active;
    }

    // ── Methods ─────────────────────────────────────────────────

    public function deleteImage(): void
    {
        if ($this->image_path) {
            Storage::disk('public')->delete($this->image_path);
        }
    }
}
