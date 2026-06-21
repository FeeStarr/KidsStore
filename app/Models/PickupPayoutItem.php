<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PickupPayoutItem extends Model
{
    protected $fillable = ['pickup_payout_id','order_item_id','order_id','fee_amount'];

    protected $casts = [
        'fee_amount' => 'decimal:2',
    ];

    public function payout(): BelongsTo
    {
        return $this->belongsTo(PickupPayout::class, 'pickup_payout_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
