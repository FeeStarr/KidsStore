<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventory extends Model
{
    protected $fillable = ['product_id', 'product_variant_id', 'quantity', 'quantity_on_hand', 'reorder_level'];

    protected $casts = [
        'quantity' => 'integer',
        'quantity_on_hand' => 'integer',
        'reorder_level' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function isLowStock(): bool
    {
        return $this->current_quantity <= $this->reorder_level;
    }

    public function getCurrentQuantityAttribute(): int
    {
        if ($this->quantity_on_hand !== null) {
            return (int) $this->quantity_on_hand;
        }
        return (int) $this->quantity;
    }

    public function setQuantityAttribute($value): void
    {
        $qty = (int) $value;
        $this->attributes['quantity'] = $qty;
        $this->attributes['quantity_on_hand'] = $qty;
    }

    public function setQuantityOnHandAttribute($value): void
    {
        $qty = (int) $value;
        $this->attributes['quantity_on_hand'] = $qty;
        $this->attributes['quantity'] = $qty;
    }
}
