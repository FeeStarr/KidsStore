<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryCharge extends Model
{
    public const UNAVAILABLE_MESSAGE = 'Delivery is not available for the selected location right now. Please choose a different location or a pickup station.';

    protected $fillable = [
        'delivery_agent_id', 'delivery_location_id', 'amount', 'is_active', 'effective_from',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_active' => 'boolean',
        'effective_from' => 'date',
    ];

    /**
     * The active charge with an active agent covering this location,
     * or null when the location is not serviceable (used to block order
     * placement when no delivery agent is available for the location).
     */
    public static function activeWithAgentFor(int $locationId): ?self
    {
        $charge = static::query()
            ->where('delivery_location_id', $locationId)
            ->where('is_active', true)
            ->whereHas('location', fn ($q) => $q->where('is_active', true))
            ->with('agent')
            ->first();

        if (! $charge || ! $charge->agent || ! $charge->agent->is_active) {
            return null;
        }

        return $charge;
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(DeliveryAgent::class, 'delivery_agent_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(DeliveryLocation::class, 'delivery_location_id');
    }
}
