<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'sku'              => $this->sku,
            'name'             => $this->name,
            'options'          => $this->options,
            'options_label'    => $this->options_label,
            'selling_price'    => (float) $this->selling_price,
            'discount'         => (float) $this->discount,
            'effective_discount' => (float) $this->effective_discount,
            'net_price'        => (float) $this->net_price,
            'stock_quantity'   => (int) $this->stock_quantity,
            'is_active'        => (bool) $this->is_active,
            'image'            => new ProductImageResource($this->whenLoaded('image')),
            'age_range'        => $this->whenLoaded('ageRange', fn () => [
                'id'   => $this->ageRange->id,
                'name' => $this->ageRange->name,
            ]),
            'size'             => $this->whenLoaded('sizeRef', fn () => [
                'id'   => $this->sizeRef->id,
                'name' => $this->sizeRef->name,
            ]),
            'color'            => $this->whenLoaded('colorRef', fn () => [
                'id'   => $this->colorRef->id,
                'name' => $this->colorRef->name,
                'hex'  => $this->colorRef->hex,
            ]),
        ];
    }
}
