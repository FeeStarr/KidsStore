<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_name', 'bank_account_name', 'bank_account_number', 'instructions', 'is_active', 'is_default'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }
}
