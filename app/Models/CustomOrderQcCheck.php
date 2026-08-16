<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomOrderQcCheck extends Model
{
    protected $fillable = [
        'custom_order_id', 'check_item', 'passed', 'notes', 'checked_by', 'checked_at',
    ];

    protected $casts = [
        'passed' => 'boolean',
        'checked_at' => 'datetime',
    ];

    public function customOrder(): BelongsTo
    {
        return $this->belongsTo(CustomOrder::class);
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
