<?php

namespace App\Services;

use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;

/**
 * Session-backed shopping cart. Stores [product_variant_id => qty] in the
 * session and exposes hydrated line objects with the resolved variant,
 * its parent product and current price/discount.
 */
class CartService
{
    private const KEY = 'cart';

    public function add(int $variantId, int $quantity = 1): void
    {
        $cart = $this->raw();
        $cart[$variantId] = ($cart[$variantId] ?? 0) + max(1, $quantity);
        $this->save($cart);
    }

    public function update(int $variantId, int $quantity): void
    {
        $cart = $this->raw();
        if ($quantity <= 0) {
            unset($cart[$variantId]);
        } else {
            $cart[$variantId] = $quantity;
        }
        $this->save($cart);
    }

    public function remove(int $variantId): void
    {
        $cart = $this->raw();
        unset($cart[$variantId]);
        $this->save($cart);
    }

    public function clear(): void
    {
        Session::forget(self::KEY);
    }

    public function isEmpty(): bool
    {
        return empty($this->raw());
    }

    public function count(): int
    {
        return array_sum($this->raw());
    }

    public function getQty(int $variantId): int
    {
        return (int) ($this->raw()[$variantId] ?? 0);
    }

    /**
     * Hydrate cart contents into a collection of line objects.
     */
    public function items(): Collection
    {
        $raw = $this->raw();
        if (empty($raw)) {
            return collect();
        }

        $variants = ProductVariant::with(['product.primaryImage', 'inventory', 'image'])
            ->whereIn('id', array_keys($raw))
            ->get()
            ->keyBy('id');

        return collect($raw)->map(function (int $qty, int $id) use ($variants) {
            $variant = $variants->get($id);
            if (! $variant) {
                return null;
            }

            $unit      = (float) $variant->selling_price;
            $discount  = (float) $variant->discount;
            $netUnit   = $unit * (1 - $discount / 100);
            $lineTotal = $netUnit * $qty;

            return (object) [
                'variant'    => $variant,
                'product'    => $variant->product,
                'quantity'   => $qty,
                'unit_price' => $unit,
                'discount'   => $discount,
                'net_unit'   => $netUnit,
                'line_total' => $lineTotal,
            ];
        })->filter()->values();
    }

    public function subtotal(): float
    {
        return (float) $this->items()->sum('line_total');
    }

    private function raw(): array
    {
        return (array) Session::get(self::KEY, []);
    }

    private function save(array $cart): void
    {
        Session::put(self::KEY, $cart);
    }
}
