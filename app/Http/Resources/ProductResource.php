<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'slug'           => $this->slug,
            'sku'            => $this->sku,
            'description'    => $this->description,
            'selling_price'  => (float) $this->selling_price,
            'discount'       => (float) $this->discount,
            'net_price'      => (float) $this->net_price,
            'price_from'     => $this->price_from !== null ? (float) $this->price_from : null,
            'price_to'       => $this->price_to !== null ? (float) $this->price_to : null,
            'stock_quantity' => (int) $this->stock_quantity,
            'is_active'      => (bool) $this->is_active,
            'is_returnable'  => (bool) $this->is_returnable,
            'gender'         => $this->gender,
            'age_group'      => $this->age_group,
            'average_rating' => $this->average_rating,
            'reviews_count'  => $this->reviews_count,
            'catalog_image'  => $this->catalog_image,
            'available_colors' => $this->whenLoaded('variants', function () {
                return $this->variants->filter(fn ($v) => $v->is_active && $v->colorRef)
                    ->pluck('colorRef.name')->filter()->unique()->values()->all();
            }, []),
            'available_sizes'  => $this->whenLoaded('variants', function () {
                return $this->variants->filter(fn ($v) => $v->is_active && $v->sizeRef)
                    ->pluck('sizeRef.name')->filter()->unique()->values()->all();
            }, []),
            'category'       => new CategoryResource($this->whenLoaded('category')),
            'brand'          => new BrandResource($this->whenLoaded('brandRef')),
            'images'         => ProductImageResource::collection($this->whenLoaded('images')),
            'variants'       => ProductVariantResource::collection($this->whenLoaded('variants')),
        ];
    }
}
