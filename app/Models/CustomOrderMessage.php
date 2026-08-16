<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomOrderMessage extends Model
{
    public $timestamps = false;

    const SENDER_CUSTOMER = 'customer';
    const SENDER_ADMIN = 'admin';
    const SENDER_STAFF = 'staff';

    protected $fillable = [
        'custom_order_id', 'sender_type', 'sender_id', 'message',
        'is_customer_visible', 'created_at', 'read_at',
    ];

    protected $casts = [
        'is_customer_visible' => 'boolean',
        'created_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function customOrder(): BelongsTo
    {
        return $this->belongsTo(CustomOrder::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function markRead(): void
    {
        if (is_null($this->read_at)) {
            $this->update(['read_at' => now()]);
        }
    }
}
