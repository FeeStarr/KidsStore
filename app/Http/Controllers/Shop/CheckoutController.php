<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\PickupStation;
use App\Models\PaymentMethod;
use Illuminate\Validation\Rule;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckoutController extends Controller
{
    public function __construct(private CartService $cart, private OrderService $orders, private PaymentService $payments)
    {
    }

    public function show()
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('shop.cart.index')->with('error', 'Your cart is empty.');
        }

        $pickupStations = PickupStation::where('is_active', true)->orderBy('name')->get();

        $methods = PaymentMethod::where('is_active', true)->orderBy('label')->get();

        return view('shop.checkout.show', [
            'items'          => $this->cart->items(),
            'subtotal'       => $this->cart->subtotal(),
            'customer'       => Auth::user(),
            'pickupStations' => $pickupStations,
            'paymentMethods' => $methods,
        ]);
    }

    public function place(Request $request): RedirectResponse
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('shop.cart.index')->with('error', 'Your cart is empty.');
        }

        $active = PaymentMethod::where('is_active', true)->pluck('key')->toArray();
        if (empty($active)) {
            $active = ['transfer'];
        }

        $data = $request->validate([
            'delivery_method'   => ['required', 'in:delivery,pickup'],
            'payment_method'    => ['nullable', Rule::in($active)],
            'phone'             => ['required', 'string', 'max:30'],
            'address'           => ['required_if:delivery_method,delivery', 'nullable', 'string', 'max:500'],
            'pickup_station_id' => ['required_if:delivery_method,pickup', 'nullable', 'exists:pickup_stations,id'],
            'note'              => ['nullable', 'string', 'max:500'],
            'shipping_fee'      => ['nullable', 'numeric', 'min:0'],
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

        $order = $this->orders->create([
            'customer_id'       => $customer->id,
            'order_date'        => now()->toDateString(),
            'status'            => 'ordered',
            'delivery_method'   => $data['delivery_method'],
            'pickup_station_id' => $data['delivery_method'] === 'pickup' ? ($data['pickup_station_id'] ?? null) : null,
            'delivery_address'  => $data['delivery_method'] === 'delivery' ? ($data['address'] ?? null) : null,
            'shipping_fee'      => (float) ($data['shipping_fee'] ?? 0),
            'note'              => trim($data['note'] ?? '') ?: null,
            'items'             => $items,
        ]);

        $this->cart->clear();

        // If customer selected Pay-on-delivery (bank transfer), record a zero-amount
        // payment for bookkeeping and mark the order pending confirmation.
        if (($data['payment_method'] ?? null) === 'transfer') {
            $this->payments->record($order, [
                'payment_date' => now()->toDateString(),
                'amount'       => 0.00,
                'method'       => 'transfer',
                'note'         => 'Payment on delivery (transfer)',
            ]);
            $this->orders->markPendingConfirmation($order);
        }

        return redirect()->route('shop.account.orders.show', $order)
            ->with('success', 'Order placed! Order Number: '.$order->reference);
    }
}
