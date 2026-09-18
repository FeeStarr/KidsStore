<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CustomCreation extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'image_path',
        'price',
        'is_price_from',
        'description',
        'category',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_price_from' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    const CATEGORIES = [];

    // ── Scopes ──────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    // ── Accessors ───────────────────────────────────────────────

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image_path) {
            return null;
        }

        return Storage::disk('public')->url($this->image_path);
    }

    // ── Methods ─────────────────────────────────────────────────

    public function deleteImage(): void
    {
        if ($this->image_path) {
            Storage::disk('public')->delete($this->image_path);
        }
    }
}
