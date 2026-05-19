<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
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

        return view('shop.checkout.show', [
            'items'    => $this->cart->items(),
            'subtotal' => $this->cart->subtotal(),
            'customer' => Auth::user(),
        ]);
    }

    public function place(Request $request): RedirectResponse
    {
        if ($this->cart->isEmpty()) {
            return redirect()->route('shop.cart.index')->with('error', 'Your cart is empty.');
        }

        $data = $request->validate([
            'phone'        => ['required', 'string', 'max:30'],
            'address'      => ['required', 'string', 'max:500'],
            'note'         => ['nullable', 'string', 'max:500'],
            'shipping_fee' => ['nullable', 'numeric', 'min:0'],
        ]);

        $customer = Auth::user();
        // Update customer profile address/phone with the latest values.
        $customer->fill(['phone' => $data['phone'], 'address' => $data['address']])->save();

        $items = $this->cart->items()->map(fn ($l) => [
            'product_id'         => $l->product->id,
            'product_variant_id' => $l->variant->id,
            'quantity'           => $l->quantity,
            'unit_price'         => $l->unit_price,
            'discount'           => $l->discount,
        ])->all();

        $order = $this->orders->create([
            'customer_id'  => $customer->id,
            'order_date'   => now()->toDateString(),
            'status'       => 'order placed',
            'shipping_fee' => (float) ($data['shipping_fee'] ?? 0),
            'note'         => $data['note'] ?? null,
            'items'        => $items,
        ]);

        $this->cart->clear();

        return redirect()->route('shop.account.orders.show', $order)
            ->with('success', 'Order placed! Reference: '.$order->reference);
    }
}
