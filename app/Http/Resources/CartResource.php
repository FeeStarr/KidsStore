<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var \App\Services\CartService $cart */
        $cart = $this->resource;

        $items = $cart->items();
        $subtotal = $cart->subtotal();
        $coupon = $cart->coupon();
        $couponDiscount = $cart->couponDiscount();

        return [
            'items'           => CartItemResource::collection($items),
            'items_count'     => $cart->count(),
            'subtotal'        => $subtotal,
            'coupon'          => $coupon ? [
                'code'            => $coupon->code,
                'name'            => $coupon->name,
                'discount_type'   => $coupon->discount_type,
                'discount_value'  => (float) $coupon->discount_value,
                'discount_label'  => $coupon->discount_label,
                'discount_amount' => $couponDiscount,
            ] : null,
            'coupon_discount' => $couponDiscount,
            'total'           => $subtotal - $couponDiscount,
        ];
    }
}
