<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\OrderResource;
use App\Models\DeliveryCharge;
use App\Models\Setting;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CheckoutController extends BaseController
{
    public function __construct(
        private CartService $cart,
        private OrderService $orders,
    ) {}

    /**
     * POST /api/v1/checkout
     *
     * Mirrors Shop\CheckoutController::createOrder() business flow so that
     * API-created orders follow exactly the same lifecycle as website orders.
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
            'note'                 => ['nullable', 'string', 'max:500'],
            'address'              => ['required_if:delivery_method,delivery', 'nullable', 'string', 'max:500'],
            'delivery_location_id' => ['required_if:delivery_method,delivery', 'nullable', 'integer', 'exists:delivery_locations,id'],
            'pickup_station_id'    => ['required_if:delivery_method,pickup', 'nullable', 'integer', 'exists:pickup_stations,id'],
        ];

        $data = $request->validate($rules);

        $customer = $request->user();
        $customer->fill(['phone' => $data['phone']])->save();

        $items = $this->cart->items()->map(fn ($l) => [
            'product_id'          => $l->product->id,
            'product_variant_id'  => $l->variant->id,
            'quantity'            => $l->quantity,
            'unit_price'          => $l->unit_price,
            'original_unit_price' => $l->original_unit_price,
            'discount'            => $l->discount,
            'discount_amount'     => $l->discount_amount,
            'deal_id'             => $l->deal_id,
        ])->all();

        $shippingFee = 0;
        $deliveryChargeAmount = null;
        $deliveryAgentId = null;
        $deliveryLocationId = null;

        if ($data['delivery_method'] === 'delivery' && ! empty($data['delivery_location_id'])) {
            $deliveryLocationId = (int) $data['delivery_location_id'];
            $charge = DeliveryCharge::where('delivery_location_id', $deliveryLocationId)
                ->where('is_active', true)
                ->with('agent')
                ->first();

            if ($charge && $charge->agent && $charge->agent->is_active) {
                $shippingFee = (float) $charge->amount;
                $deliveryChargeAmount = $shippingFee;
                $deliveryAgentId = $charge->delivery_agent_id;
            }
        }

        if ($shippingFee === 0 && $data['delivery_method'] === 'delivery') {
            $shippingFeeSetting = Setting::get('shipping_fee', null);
            if ($shippingFeeSetting !== null && $shippingFeeSetting !== '') {
                $shippingFee = (float) $shippingFeeSetting;
            }
        }

        $orderStatus = $data['payment_method'] === 'pay_now'
            ? 'pending payment'
            : 'pending confirmation';

        try {
            $order = $this->orders->create([
                'customer_id'            => $customer->id,
                'guest_name'             => null,
                'guest_email'            => null,
                'guest_phone'            => $data['phone'],
                'lookup_token'           => Str::random(64),
                'order_date'             => now()->toDateTimeString(),
                'status'                 => $orderStatus,
                'delivery_method'        => $data['delivery_method'],
                'payment_method'         => $data['payment_method'],
                'pickup_station_id'      => $data['delivery_method'] === 'pickup' ? ($data['pickup_station_id'] ?? null) : null,
                'delivery_address'       => $data['delivery_method'] === 'delivery' ? ($data['address'] ?? null) : null,
                'delivery_agent_id'      => $deliveryAgentId,
                'delivery_location_id'   => $deliveryLocationId,
                'delivery_charge_amount' => $deliveryChargeAmount,
                'shipping_fee'           => $shippingFee,
                'note'                   => trim($data['note'] ?? '') ?: null,
                'coupon_id'              => $this->cart->couponId(),
                'items'                  => $items,
            ]);
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        $this->cart->clear();

        return $this->createdResponse(
            new OrderResource($order->load(['items.product', 'items.variant', 'pickupStation', 'deliveryLocation'])),
            'Order placed successfully.',
        );
    }
}
