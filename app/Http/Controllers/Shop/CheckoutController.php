<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Models\PickupStation;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckoutController extends Controller
{
    public function __construct(private CartService $cart, private OrderService $orders)
    {
    }

    public function show()
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('shop.cart.index')->with('error', 'Your cart is empty.');
        }

        $pickupStations = PickupStation::where('is_active', true)->where('is_available', true)->orderBy('name')->get();
        $paymentMethods = PaymentMethod::where('is_active', true)->orderBy('key')->get();

        return view('shop.checkout.show', [
            'items'          => $this->cart->items(),
            'subtotal'       => $this->cart->subtotal(),
            'customer'       => Auth::user(),
            'pickupStations' => $pickupStations,
            'paymentMethods' => $paymentMethods,
        ]);
    }

    public function place(Request $request): RedirectResponse
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('shop.cart.index')->with('error', 'Your cart is empty.');
        }

        $data = $request->validate([
            'delivery_method'   => ['required', 'in:delivery,pickup'],
            'phone'             => ['required', 'string', 'max:30'],
            'address'           => ['required_if:delivery_method,delivery', 'nullable', 'string', 'max:500'],
            'pickup_station_id' => ['required_if:delivery_method,pickup', 'nullable', 'exists:pickup_stations,id'],
            'note'              => ['nullable', 'string', 'max:500'],
            'shipping_fee'      => ['nullable', 'numeric', 'min:0'],
            'payment_method'    => ['nullable', 'string', 'exists:payment_methods,key'],
        ]);

        $customer = Auth::user();
        $customer->fill(['phone' => $data['phone']])->save();

        $items = $this->cart->items()->map(fn ($l) => [
            'product_id'         => $l->product->id,
            'product_variant_id' => $l->variant->id,
            'quantity'           => $l->quantity,
            'unit_price'         => $l->unit_price,
            'discount'           => $l->discount,
        ])->all();

        // Determine authoritative per-item shipping fee from general site settings
        $shippingFee = 0;
        $shippingFeeSetting = \App\Models\Setting::get('shipping_fee', null);
        if ($shippingFeeSetting !== null && $shippingFeeSetting !== '') {
            $shippingFee = (float) $shippingFeeSetting;
        } else {
            $shippingFee = (float) ($data['shipping_fee'] ?? 0);
        }

        // Determine order status based on payment method
        $orderStatus = ($data['payment_method'] ?? '') === 'instant_bank_transfer'
            ? 'pending payment'
            : 'confirmed';

        try {
            $order = $this->orders->create([
                'customer_id'       => $customer->id,
                'order_date'        => now()->toDateString(),
                'status'            => $orderStatus,
                'delivery_method'   => $data['delivery_method'],
                'payment_method'    => $data['payment_method'] ?? null,
                'pickup_station_id' => $data['delivery_method'] === 'pickup' ? ($data['pickup_station_id'] ?? null) : null,
                'delivery_address'  => $data['delivery_method'] === 'delivery' ? ($data['address'] ?? null) : null,
                'shipping_fee'      => $shippingFee,
                'note'              => trim($data['note'] ?? '') ?: null,
                'items'             => $items,
            ]);
        } catch (\RuntimeException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', $e->getMessage() ?: 'One or more items are out of stock. Please adjust your cart and try again.');
        }

        $this->cart->clear();

        $redirect = redirect()->route('shop.account.orders.show', $order)
            ->with('success', 'Order placed! Order Number: '.$order->reference);

        if (($data['payment_method'] ?? '') === 'instant_bank_transfer') {
            $redirect = $redirect->with('show_pay_now', true);
        }

        return $redirect;
    }
}
