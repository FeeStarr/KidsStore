<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var object $item */
        $item = $this->resource;

        return [
            'line_key'          => $item->line_key,
            'quantity'          => $item->quantity,
            'unit_price'        => $item->unit_price,
            'original_unit_price' => $item->original_unit_price,
            'discount'          => $item->discount,
            'discount_amount'   => $item->discount_amount,
            'net_unit'          => $item->net_unit,
            'line_total'        => $item->line_total,
            'age_group'         => $item->age_group,
            'selected_size'     => $item->selected_size,
            'deal_id'           => $item->deal_id,
            'variant'           => new ProductVariantResource($item->variant),
            'product'           => new ProductResource($item->product),
        ];
    }
}
