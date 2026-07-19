<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnAuditLog extends Model
{
    protected $fillable = [
        'refund_request_id',
        'action',
        'user_id',
        'details',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function refundRequest(): BelongsTo
    {
        return $this->belongsTo(RefundRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
