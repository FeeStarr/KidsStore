<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CustomOrderFile extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'custom_order_id', 'file_type', 'file_path', 'original_filename',
        'mime_type', 'file_size', 'uploaded_by', 'created_at',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'created_at' => 'datetime',
    ];

    public function customOrder(): BelongsTo
    {
        return $this->belongsTo(CustomOrder::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function url(): string
    {
        return Storage::disk('custom_orders')->url($this->file_path);
    }

    public function temporaryUrl(int $minutes = 30): string
    {
        return Storage::disk('custom_orders')->temporaryUrl($this->file_path, now()->addMinutes($minutes));
    }
}
