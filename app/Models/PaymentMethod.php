<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $fillable = ['key', 'label', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
