<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PickupReport extends Model
{
    protected $fillable = [
        'pickup_station_id',
        'order_id',
        'order_item_id',
        'type',
        'description',
        'status',
        'admin_notes',
    ];

    public const TYPES = [
        'missing_order'     => 'Missing Order',
        'missing_item'      => 'Missing Item',
        'damaged_item'      => 'Damaged Item',
        'wrong_item'        => 'Wrong Item',
        'customer_no_show'  => 'Customer No-Show',
        'other'             => 'Other',
    ];

    public const STATUSES = [
        'open'          => 'Open',
        'investigating' => 'Investigating',
        'resolved'      => 'Resolved',
        'dismissed'     => 'Dismissed',
    ];

    public function station(): BelongsTo
    {
        return $this->belongsTo(PickupStation::class, 'pickup_station_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
