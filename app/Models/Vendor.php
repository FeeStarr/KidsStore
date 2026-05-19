<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    protected $fillable = [
        'user_id',
        'store_name',
        'store_description',
        'commission_rate',
        'is_approved',
        'bank_name',
        'bank_account',
        'account_holder_name',
    ];

    protected $casts = [
        'is_approved' => 'boolean',
        'commission_rate' => 'decimal:2',
    ];

    /**
     * Get the user that owns this vendor profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all products from this vendor.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Check if vendor is approved.
     */
    public function isApproved(): bool
    {
        return (bool) $this->is_approved;
    }

    /**
     * Approve this vendor.
     */
    public function approve(): void
    {
        $this->update(['is_approved' => true]);
    }

    /**
     * Reject this vendor.
     */
    public function reject(): void
    {
        $this->update(['is_approved' => false]);
    }
}
