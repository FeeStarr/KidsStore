<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private CartService $cart)
    {
    }

    public function index()
    {
        return view('shop.cart.index', [
            'items'    => $this->cart->items(),
            'subtotal' => $this->cart->subtotal(),
        ]);
    }

    public function add(Request $request, ProductVariant $variant): RedirectResponse|JsonResponse
    {
        $request->validate(['quantity' => ['nullable', 'integer', 'min:1', 'max:999']]);

        abort_unless($variant->is_active && $variant->product->is_active, 404);

        $this->cart->add($variant->id, (int) ($request->input('quantity', 1)));

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success'       => true,
                'message'       => 'Added to cart.',
                'cart_count'    => $this->cart->count(),
                'variant_qty'   => $this->cart->getQty($variant->id),
                'variant_stock' => (int) ($variant->inventory->quantity ?? 0),
            ]);
        }

        return back()->with('success', $variant->product->name.' added to cart.');
    }

    public function update(Request $request, ProductVariant $variant): RedirectResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:999']]);
        $this->cart->update($variant->id, (int) $data['quantity']);

        return back();
    }

    public function remove(Request $request, ProductVariant $variant): RedirectResponse|JsonResponse
    {
        $this->cart->remove($variant->id);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success'       => true,
                'message'       => 'Removed from cart.',
                'cart_count'    => $this->cart->count(),
                'variant_qty'   => 0,
                'variant_stock' => (int) ($variant->inventory->quantity ?? 0),
            ]);
        }

        return back();
    }

    public function clear(): RedirectResponse
    {
        $this->cart->clear();

        return back();
    }
}
