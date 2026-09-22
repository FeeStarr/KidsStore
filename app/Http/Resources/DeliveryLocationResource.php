<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryLocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'state'       => $this->state,
            'description' => $this->description,
            'is_active'   => (bool) $this->is_active,
            'charges'     => DeliveryChargeResource::collection($this->whenLoaded('charges')),
        ];
    }
}
