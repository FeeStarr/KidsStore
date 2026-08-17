<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $fillable = [
        'name', 'contact_name', 'email', 'phone', 'address', 'is_active',
        'social_whatsapp', 'social_instagram', 'social_facebook',
        'social_tiktok', 'social_twitter', 'social_website',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }
}
