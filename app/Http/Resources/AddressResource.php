<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'label'       => $this->label,
            'line1'       => $this->line1,
            'line2'       => $this->line2,
            'city'        => $this->city,
            'state'       => $this->state,
            'postal_code' => $this->postal_code,
            'country'     => $this->country,
            'is_default'  => (bool) $this->is_default,
            'full_address' => trim(implode(', ', array_filter([
                $this->line1,
                $this->line2,
                $this->city,
                $this->state,
                $this->postal_code,
                $this->country,
            ]))),
        ];
    }
}
