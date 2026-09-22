<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DealResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'title'           => $this->title,
            'slug'            => $this->slug,
            'description'     => $this->description,
            'discount_type'   => $this->discount_type,
            'discount_value'  => (float) $this->discount_value,
            'discount_label'  => $this->discount_label,
            'badge_text'      => $this->badge_text,
            'is_featured'     => (bool) $this->is_featured,
            'is_live'         => $this->is_live,
            'starts_at'       => $this->starts_at?->toISOString(),
            'ends_at'         => $this->ends_at?->toISOString(),
            'banner_image'    => $this->banner_image,
            'thumbnail_image' => $this->thumbnail_image,
            'products'        => ProductResource::collection($this->whenLoaded('products')),
        ];
    }
}
