<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomOrderQuote extends Model
{
    public $timestamps = false;

    const STATUS_DRAFT = 'draft';
    const STATUS_SUPERSEDED = 'superseded';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'custom_order_id', 'version',
        'base_price', 'fabric_cost', 'customization_cost', 'embellishment_cost',
        'measurement_fee', 'rush_fee', 'delivery_fee', 'discount', 'total',
        'breakdown', 'valid_until', 'notes', 'status', 'created_by', 'created_at', 'approved_at', 'reminder_sent',
    ];

    protected $casts = [
        'version' => 'integer',
        'base_price' => 'decimal:2',
        'fabric_cost' => 'decimal:2',
        'customization_cost' => 'decimal:2',
        'embellishment_cost' => 'decimal:2',
        'measurement_fee' => 'decimal:2',
        'rush_fee' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'breakdown' => 'array',
        'valid_until' => 'datetime',
        'created_at' => 'datetime',
        'approved_at' => 'datetime',
        'reminder_sent' => 'boolean',
    ];

    public function customOrder(): BelongsTo
    {
        return $this->belongsTo(CustomOrder::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isExpired(): bool
    {
        return $this->valid_until && $this->valid_until->isPast();
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }
}
