<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\CartResource;
use App\Models\DeliveryCharge;
use App\Models\Order;
use App\Models\DeliveryLocation;
use App\Models\PickupStation;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CheckoutController extends BaseController
{
    public function __construct(
        private CartService $cart,
        private OrderService $orders,
    ) {}

    /**
     * POST /api/v1/checkout
     */
    public function place(Request $request): JsonResponse
    {
        if ($this->cart->isEmpty()) {
            return $this->errorResponse('Your cart is empty.', 422);
        }

        $rules = [
            'delivery_method'      => ['required', Rule::in(['delivery', 'pickup'])],
            'phone'                => ['required', 'string', 'max:30'],
            'payment_method'       => ['required', Rule::in(['pay_now', 'pay_on_delivery'])],
            'address'              => ['nullable', 'string', 'max:500'],
            'delivery_location_id' => ['nullable', 'integer', 'exists:delivery_locations,id'],
            'pickup_station_id'    => ['nullable', 'integer', 'exists:pickup_stations,id'],
            'note'                 => ['nullable', 'string', 'max:500'],
        ];

        $rules['address'] = ['required_if:delivery_method,delivery', 'nullable', 'string', 'max:500'];
        $rules['delivery_location_id'] = ['required_if:delivery_method,delivery', 'nullable', 'integer', 'exists:delivery_locations,id'];
        $rules['pickup_station_id'] = ['required_if:delivery_method,pickup', 'nullable', 'integer', 'exists:pickup_stations,id'];

        $data = $request->validate($rules);

        // Calculate shipping fee server-side
        $shippingFee = 0;
        if ($data['delivery_method'] === 'delivery' && ! empty($data['delivery_location_id'])) {
            $charge = DeliveryCharge::where('delivery_location_id', $data['delivery_location_id'])
                ->where('is_active', true)
                ->first();
            $shippingFee = $charge ? (float) $charge->amount : 0;
        }

        // Place order using existing OrderService
        $order = $this->orders->create($request->user(), [
            'items'               => $this->cart->items()->toArray(),
            'delivery_method'     => $data['delivery_method'],
            'payment_method'      => $data['payment_method'],
            'phone'               => $data['phone'],
            'address'             => $data['address'] ?? null,
            'delivery_location_id' => $data['delivery_location_id'] ?? null,
            'pickup_station_id'   => $data['pickup_station_id'] ?? null,
            'note'                => $data['note'] ?? null,
            'shipping_fee'        => $shippingFee,
            'coupon_id'           => $this->cart->couponId(),
            'coupon_discount'     => $this->cart->couponDiscount(),
        ]);

        $this->cart->clear();

        return $this->createdResponse(
            new \App\Http\Resources\OrderResource($order->load(['items.product', 'items.variant', 'pickupStation', 'deliveryLocation'])),
            'Order placed successfully.',
        );
    }
}
