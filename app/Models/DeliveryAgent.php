<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryAgent extends Model
{
    protected $fillable = [
        'name', 'contact_name', 'phone', 'email', 'notes', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function charges(): HasMany
    {
        return $this->hasMany(DeliveryCharge::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
