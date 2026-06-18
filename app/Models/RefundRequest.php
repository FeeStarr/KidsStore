<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class RefundRequest extends Model
{
    // Refund window in days after delivery
    public const REFUND_WINDOW_DAYS = 7;

    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_FAILED   = 'failed';

    public const REASONS = [
        'wrong_item'    => 'Wrong item received',
        'damaged'       => 'Item arrived damaged',
        'not_received'  => 'Item not received',
        'changed_mind'  => 'Changed my mind',
        'other'         => 'Other',
    ];

    protected $fillable = [
        'order_id', 'order_item_id', 'quantity', 'amount',
        'status', 'reason', 'details', 'evidence_path',
        'admin_note', 'reviewed_by', 'reviewed_at',
        'opay_refund_no', 'opay_payload',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'quantity'    => 'integer',
        'reviewed_at' => 'datetime',
        'opay_payload'=> 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getReasonLabelAttribute(): string
    {
        return self::REASONS[$this->reason] ?? ucfirst($this->reason);
    }

    public function getEvidenceUrlAttribute(): ?string
    {
        return $this->evidence_path
            ? Storage::disk('public')->url($this->evidence_path)
            : null;
    }

    public function getScopeLabel(): string
    {
        if ($this->orderItem) {
            $item = $this->orderItem;
            return ($item->product?->name ?? 'Item') .
                   ($item->variant?->options_label ? ' — ' . $item->variant->options_label : '') .
                   ' (×' . $this->quantity . ')';
        }
        return 'Full Order';
    }

    public function isPending(): bool  { return $this->status === self::STATUS_PENDING; }
    public function isApproved(): bool { return $this->status === self::STATUS_APPROVED; }
    public function isRefunded(): bool { return $this->status === self::STATUS_REFUNDED; }
}
