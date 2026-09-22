<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomCreationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'title'          => $this->title,
            'image_url'      => $this->image_url,
            'price'          => (float) $this->price,
            'is_price_from'  => (bool) $this->is_price_from,
            'description'    => $this->description,
            'category'       => $this->category,
        ];
    }
}
