<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'quantity'          => $this->quantity,
            'cancelled_quantity' => $this->cancelled_quantity,
            'unit_price'        => (float) $this->unit_price,
            'original_unit_price' => (float) $this->original_unit_price,
            'discount'          => (float) $this->discount,
            'discount_amount'   => (float) $this->discount_amount,
            'coupon_discount'   => (float) $this->coupon_discount,
            'line_total'        => (float) $this->line_total,
            'selected_age_group' => $this->selected_age_group,
            'selected_size'     => $this->selected_size,
            'pickup_status'     => $this->pickup_status,
            'product'           => new ProductResource($this->whenLoaded('product')),
            'variant'           => new ProductVariantResource($this->whenLoaded('variant')),
        ];
    }
}
