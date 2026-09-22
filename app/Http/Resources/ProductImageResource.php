<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'url'         => $this->url,
            'webp_url'    => $this->webp_url,
            'alt_text'    => $this->alt_text,
            'is_primary'  => (bool) $this->is_primary,
            'sort_order'  => (int) $this->sort_order,
        ];
    }
}
