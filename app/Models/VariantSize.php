<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VariantSize extends Model
{
    protected $table = 'variant_sizes';

    protected $fillable = [
        'product_variant_id', 'size', 'sku', 'quantity', 'reorder_level',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'reorder_level' => 'integer',
    ];

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * Human-readable label: "Large (5-6 years)" or just "Large" if no size specified.
     */
    public function getDisplayLabelAttribute(): string
    {
        if ($this->size) {
            return (string) $this->size;
        }
        return "Size #{$this->id}";
    }

    /**
     * Full display including size and sku for dropdowns/selectors.
     */
    public function getFullLabelAttribute(): string
    {
        $label = $this->display_label;
        if ($this->sku) {
            $label .= " ({$this->sku})";
        }
        return $label;
    }

    /**
     * Check if this size is in stock.
     */
    public function getIsInStockAttribute(): bool
    {
        return (int) ($this->quantity ?? 0) > 0;
    }

    /**
     * Check if stock is low (at or below reorder level).
     */
    public function getIsLowStockAttribute(): bool
    {
        return (int) ($this->quantity ?? 0) <= (int) ($this->reorder_level ?? 0);
    }
}
