<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function __construct(private CartService $cart)
    {
    }

    public function index()
    {
        $couponDiscount = 0.0;
        $coupon = $this->cart->coupon();
        if ($coupon) {
            $couponDiscount = $this->cart->couponDiscount();
        }

        return view('shop.cart.index', [
            'items'           => $this->cart->items(),
            'subtotal'        => $this->cart->subtotal(),
            'coupon'          => $coupon,
            'coupon_discount' => $couponDiscount,
        ]);
    }

    public function applyCoupon(Request $request): RedirectResponse
    {
        $code = trim((string) $request->input('code', ''));

        try {
            $coupon = $this->cart->applyCoupon($code);
        } catch (ValidationException $e) {
            $message = $e->errors()['code'][0] ?? 'That coupon code could not be applied.';
            return back()->withInput()->with('error', $message);
        }

        return back()->with('success', 'Coupon applied: '.$coupon->name.'.');
    }

    public function removeCoupon(): RedirectResponse
    {
        $this->cart->removeCoupon();

        return back()->with('success', 'Coupon removed.');
    }

    public function add(Request $request, ProductVariant $variant): RedirectResponse|JsonResponse
    {
        $request->validate([
            'quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        abort_unless($variant->is_active && $variant->product->is_active, 404);

        $requestedQty = (int) ($request->input('quantity', 1));

        $variantInCart = $this->cart->getQty($variant->id);
        $variantStock  = (int) ($variant->inventory?->quantity ?? 0);

        if (($variantInCart + $requestedQty) > $variantStock) {
            $msg = "Only {$variantStock} available in stock for this option.";
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return back()->with('error', $msg);
        }

        $this->cart->add($variant->id, $requestedQty);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success'       => true,
                'message'       => 'Added to cart.',
                'cart_count'    => $this->cart->count(),
                'variant_qty'   => $this->cart->getQty($variant->id),
                'variant_stock' => $variantStock,
            ]);
        }

        return back()->with('success', $variant->product->name.' added to cart.');
    }

    public function update(Request $request, ProductVariant $variant): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:999'],
            'selected_age_group' => ['nullable', 'string', 'max:32'],
            'selected_size' => ['nullable', 'string', 'max:64'],
            'line_key' => ['nullable', 'string', 'max:120'],
        ]);

        $newQty = (int) $data['quantity'];
        $variantStock = (int) ($variant->inventory?->quantity ?? 0);
        $adjusted = false;

        if ($newQty > $variantStock) {
            $newQty = $variantStock;
            $adjusted = true;
        }

        if (! empty($data['line_key'])) {
            $this->cart->updateByLineKey((string) $data['line_key'], $newQty);
        } else {
            $selectedAgeGroup = trim((string) ($data['selected_age_group'] ?? '')) ?: null;
            $selectedSize = trim((string) ($data['selected_size'] ?? '')) ?: null;
            $this->cart->update($variant->id, $newQty, $selectedAgeGroup, $selectedSize);
        }

        if ($adjusted) {
            return back()->with('warning', "Only {$variantStock} available in stock. Quantity adjusted to {$variantStock}.");
        }

        return back();
    }

    public function remove(Request $request, ProductVariant $variant): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'selected_age_group' => ['nullable', 'string', 'max:32'],
            'selected_size' => ['nullable', 'string', 'max:64'],
            'line_key' => ['nullable', 'string', 'max:120'],
        ]);

        if (! empty($validated['line_key'])) {
            $this->cart->removeByLineKey((string) $validated['line_key']);
        } else {
            $selectedAgeGroup = trim((string) ($validated['selected_age_group'] ?? '')) ?: null;
            $selectedSize = trim((string) ($validated['selected_size'] ?? '')) ?: null;
            $this->cart->remove($variant->id, $selectedAgeGroup, $selectedSize);
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success'       => true,
                'message'       => 'Removed from cart.',
                'cart_count'    => $this->cart->count(),
                'variant_qty'   => $this->cart->getQty($variant->id),
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
