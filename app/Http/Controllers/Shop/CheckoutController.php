<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\PickupStation;
use App\Models\Setting;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    public function __construct(private CartService $cart, private OrderService $orders) {}

    public function show()
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('shop.cart.index')->with('error', 'Your cart is empty.');
        }

        $pickupStations = PickupStation::where('is_active', true)->where('is_available', true)->orderBy('name')->get();
        $paymentMethods = PaymentMethod::where('is_active', true)->orderBy('key')->get();

        $coupon = $this->cart->coupon();
        $customer = Auth::user();

        return view('shop.checkout.show', [
            'items' => $this->cart->items(),
            'subtotal' => $this->cart->subtotal(),
            'coupon' => $coupon,
            'coupon_discount' => $coupon ? $this->cart->couponDiscount() : 0.0,
            'customer' => $customer,
            'pickupStations' => $pickupStations,
            'paymentMethods' => $paymentMethods,
        ]);
    }

    public function place(Request $request): RedirectResponse
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('shop.cart.index')->with('error', 'Your cart is empty.');
        }

        $isGuest = ! Auth::check();

        $rules = [
            'delivery_method' => ['required', 'in:delivery,pickup'],
            'phone' => ['required', 'string', 'max:30'],
            'address' => ['required_if:delivery_method,delivery', 'nullable', 'string', 'max:500'],
            'pickup_station_id' => ['required_if:delivery_method,pickup', 'nullable', 'exists:pickup_stations,id'],
            'note' => ['nullable', 'string', 'max:500'],
            'shipping_fee' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string', 'exists:payment_methods,key'],
        ];

        if ($isGuest) {
            $rules['name'] = ['required', 'string', 'max:255'];
            $rules['email'] = ['required', 'email', 'max:255'];
        }

        $data = $request->validate($rules);

        $customer = Auth::user();
        $customerId = null;
        $guestName = null;
        $guestEmail = null;

        if ($customer) {
            $customer->fill(['phone' => $data['phone']])->save();
            $customerId = $customer->id;
        } else {
            $guestName = $data['name'];
            $guestEmail = $data['email'];
        }

        $items = $this->cart->items()->map(fn ($l) => [
            'product_id' => $l->product->id,
            'product_variant_id' => $l->variant->id,
            'quantity' => $l->quantity,
            'unit_price' => $l->unit_price,
            'original_unit_price' => $l->original_unit_price,
            'discount' => $l->discount,
            'discount_amount' => $l->discount_amount,
            'deal_id' => $l->deal_id,
        ])->all();

        $shippingFee = 0;
        $shippingFeeSetting = Setting::get('shipping_fee', null);
        if ($shippingFeeSetting !== null && $shippingFeeSetting !== '') {
            $shippingFee = (float) $shippingFeeSetting;
        } else {
            $shippingFee = (float) ($data['shipping_fee'] ?? 0);
        }

        $orderStatus = ($data['payment_method'] ?? '') === 'instant_bank_transfer'
            ? 'pending payment'
            : 'confirmed';

        try {
            $order = $this->orders->create([
                'customer_id' => $customerId,
                'guest_name' => $guestName,
                'guest_email' => $guestEmail,
                'guest_phone' => $data['phone'],
                'lookup_token' => Str::random(64),
                'order_date' => now()->toDateString(),
                'status' => $orderStatus,
                'delivery_method' => $data['delivery_method'],
                'payment_method' => $data['payment_method'] ?? null,
                'pickup_station_id' => $data['delivery_method'] === 'pickup' ? ($data['pickup_station_id'] ?? null) : null,
                'delivery_address' => $data['delivery_method'] === 'delivery' ? ($data['address'] ?? null) : null,
                'shipping_fee' => $shippingFee,
                'note' => trim($data['note'] ?? '') ?: null,
                'coupon_id' => $this->cart->couponId(),
                'items' => $items,
            ]);

            $this->cart->clear();
        } catch (\Throwable $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', $e->getMessage() ?: 'Could not place order. Please try again.');
        }

        if ($isGuest) {
            return redirect()->route('shop.order.track', ['token' => $order->lookup_token])
                ->with('success', 'Order placed! Order Number: '.$order->reference.'. Please enter correct email for order tracking.');
        }

        $redirect = redirect()->route('shop.account.orders.show', $order)
            ->with('success', 'Order placed! Order Number: '.$order->reference);

        if (($data['payment_method'] ?? '') === 'instant_bank_transfer') {
            $redirect = $redirect->with('show_pay_now', true);
        }

        return $redirect;
    }

    public function orderLookupForm()
    {
        return view('shop.checkout.lookup');
    }

    public function orderLookup(Request $request)
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $order = Order::where('reference', $data['reference'])
            ->where(function ($q) use ($data) {
                $q->where('guest_email', $data['email'])
                  ->orWhereHas('customer', fn ($q2) => $q2->where('email', $data['email']));
            })
            ->first();

        if (! $order) {
            return back()->with('error', 'No order found with that reference and email combination.');
        }

        if ($order->lookup_token) {
            return redirect()->route('shop.order.track', $order->lookup_token);
        }

        if (Auth::check() && (int) $order->customer_id === (int) Auth::id()) {
            return redirect()->route('shop.account.orders.show', $order);
        }

        return back()->with('error', 'Unable to access this order.');
    }

    public function orderTrack(string $token)
    {
        $order = Order::where('lookup_token', $token)->firstOrFail();

        return view('shop.checkout.track', ['order' => $order->load(['items.product', 'items.variant', 'pickupStation'])]);
    }
}
