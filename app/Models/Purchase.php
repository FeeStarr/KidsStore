<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    protected $fillable = [
        'reference', 'supplier_id', 'purchase_date', 'status',
        'total_cost_price', 'total_shipping_fee', 'total_packaging_cost',
        'total_other_costs', 'total_discount', 'grand_total', 'note',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'total_cost_price' => 'decimal:2',
        'total_shipping_fee' => 'decimal:2',
        'total_packaging_cost' => 'decimal:2',
        'total_other_costs' => 'decimal:2',
        'total_discount' => 'decimal:2',
        'grand_total' => 'decimal:2',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }
}
