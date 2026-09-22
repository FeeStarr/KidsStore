<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PickupStationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'address'           => $this->address,
            'city'              => $this->city,
            'state'             => $this->state,
            'phone'             => $this->phone,
            'email'             => $this->email,
            'instructions'      => $this->instructions,
            'is_active'         => (bool) $this->is_active,
            'is_available'      => (bool) $this->is_available,
            'pickup_shipping_fee' => (float) $this->pickup_shipping_fee,
            'full_address'      => $this->full_address,
            // access_pin is intentionally excluded
        ];
    }
}
