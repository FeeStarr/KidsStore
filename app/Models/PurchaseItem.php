<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    protected $fillable = [
        'purchase_id', 'product_id', 'product_variant_id', 'quantity',
        'cost_price', 'shipping_fee', 'packaging_cost',
        'other_costs', 'selling_price', 'discount', 'line_total',
        'pickup_fee_pct',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'cost_price' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'packaging_cost' => 'decimal:2',
        'other_costs' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'line_total' => 'decimal:2',
        'pickup_fee_pct' => 'decimal:2',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * Total landed cost per unit.
     */
    public function getUnitTotalCostAttribute(): float
    {
        return (float) $this->cost_price
            + (float) $this->shipping_fee
            + (float) $this->packaging_cost
            + (float) $this->other_costs
            - (float) $this->discount;
    }
}
