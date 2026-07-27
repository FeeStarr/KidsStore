<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentVerification extends Model
{
    protected $fillable = [
        'order_id', 'pickup_station_id', 'status', 'station_note', 'admin_note',
        'submitted_by', 'reviewed_by', 'submitted_at', 'reviewed_at', 'delay_notified_at',
    ];

    protected $casts = [
        'submitted_at'     => 'datetime',
        'reviewed_at'      => 'datetime',
        'delay_notified_at' => 'datetime',
    ];

    public const STATUS_PENDING  = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_DELAYED   = 'delayed';

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(PickupStation::class, 'pickup_station_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isDelayed(): bool
    {
        return $this->status === self::STATUS_DELAYED;
    }

    public function isVerified(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function getMinutesElapsed(): int
    {
        return (int) $this->submitted_at->diffInMinutes(now());
    }

    public function getIsOverdue(): bool
    {
        return $this->isPending() && $this->submitted_at->diffInMinutes(now()) >= 40;
    }

    public function getCountdownDisplay(): string
    {
        $elapsed = $this->getMinutesElapsed();
        $remaining = max(0, 40 - $elapsed);

        if ($remaining <= 0) {
            return 'Overdue — awaiting admin';
        }

        $mins = (int) floor($remaining / 60);
        $secs = $remaining % 60;

        return "{$mins}m {$secs}s remaining";
    }
}
