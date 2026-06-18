<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'order_id',
        'reference',
        'opay_order_no',
        'virtual_account_number',
        'virtual_bank_name',
        'amount',
        'status',
        'expires_at',
        'opay_payload',
        'last_queried_at',
    ];

    protected $casts = [
        'amount'          => 'decimal:2',
        'expires_at'      => 'datetime',
        'last_queried_at' => 'datetime',
        'opay_payload'    => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && now()->isAfter($this->expires_at);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    /** Seconds remaining until virtual account expires (0 if already expired) */
    public function secondsRemaining(): int
    {
        if (! $this->expires_at) return 0;
        return max(0, (int) now()->diffInSeconds($this->expires_at, false));
    }
}
