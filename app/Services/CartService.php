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

    public function add(int $variantId, int $quantity = 1, ?string $ageGroup = null, ?string $selectedSize = null): void
    {
        $cart = $this->raw();
        $lineKey = $this->makeLineKey($variantId, $ageGroup, $selectedSize);
        $entry = $cart[$lineKey] ?? [
            'variant_id' => $variantId,
            'age_group' => $ageGroup,
            'selected_size' => $selectedSize,
            'quantity' => 0,
        ];
        $entry['quantity'] = (int) ($entry['quantity'] ?? 0) + max(1, $quantity);
        $cart[$lineKey] = $entry;
        $this->save($cart);
    }

    public function update(int $variantId, int $quantity, ?string $ageGroup = null, ?string $selectedSize = null): void
    {
        $lineKey = $this->makeLineKey($variantId, $ageGroup, $selectedSize);
        $this->updateByLineKey($lineKey, $quantity);
    }

    public function updateByLineKey(string $lineKey, int $quantity): void
    {
        $cart = $this->raw();
        if ($quantity <= 0) {
            unset($cart[$lineKey]);
        } else {
            if (! isset($cart[$lineKey])) {
                return;
            }
            $cart[$lineKey]['quantity'] = $quantity;
        }
        $this->save($cart);
    }

    public function remove(int $variantId, ?string $ageGroup = null, ?string $selectedSize = null): void
    {
        $lineKey = $this->makeLineKey($variantId, $ageGroup, $selectedSize);
        $this->removeByLineKey($lineKey);
    }

    public function removeByLineKey(string $lineKey): void
    {
        $cart = $this->raw();
        unset($cart[$lineKey]);
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
        return (int) collect($this->raw())->sum(fn ($line) => (int) ($line['quantity'] ?? 0));
    }

    public function getQty(int $variantId): int
    {
        return (int) collect($this->raw())
            ->where('variant_id', $variantId)
            ->sum(fn ($line) => (int) ($line['quantity'] ?? 0));
    }

    public function getLineQty(int $variantId, ?string $ageGroup = null, ?string $selectedSize = null): int
    {
        $lineKey = $this->makeLineKey($variantId, $ageGroup, $selectedSize);
        return (int) (($this->raw()[$lineKey]['quantity'] ?? 0));
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
            ->whereIn('id', collect($raw)->pluck('variant_id')->unique()->values()->all())
            ->get()
            ->keyBy('id');

        return collect($raw)->map(function (array $line, string $lineKey) use ($variants) {
            $variantId = (int) ($line['variant_id'] ?? 0);
            $qty = (int) ($line['quantity'] ?? 0);
            $selectedAgeGroup = $line['age_group'] ?? null;
            $selectedSize = $line['selected_size'] ?? null;

            $variant = $variants->get($variantId);
            if (! $variant) {
                return null;
            }

            $unit      = (float) $variant->selling_price;
            $discount  = (float) $variant->effective_discount;
            $netUnit   = $unit * (1 - $discount / 100);
            $lineTotal = $netUnit * $qty;

            return (object) [
                'line_key'   => $lineKey,
                'variant'    => $variant,
                'product'    => $variant->product,
                'quantity'   => $qty,
                'age_group'  => $selectedAgeGroup,
                'selected_size' => $selectedSize,
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
        $raw = (array) Session::get(self::KEY, []);
        $normalized = [];

        // Support legacy cart format: [variant_id => qty]
        foreach ($raw as $key => $value) {
            if (is_int($value) || is_numeric($value)) {
                $variantId = (int) $key;
                $qty = (int) $value;
                $lineKey = $this->makeLineKey($variantId, null, null);
                $normalized[$lineKey] = [
                    'variant_id' => $variantId,
                    'age_group' => null,
                    'selected_size' => null,
                    'quantity' => $qty,
                ];
                continue;
            }

            if (is_array($value)) {
                $variantId = (int) ($value['variant_id'] ?? 0);
                if ($variantId <= 0) {
                    continue;
                }
                $ageGroup = $this->normalizeAgeGroup($value['age_group'] ?? null);
                $selectedSize = $this->normalizeSelectedSize($value['selected_size'] ?? null);
                $qty = max(0, (int) ($value['quantity'] ?? 0));
                if ($qty <= 0) {
                    continue;
                }
                $lineKey = $this->makeLineKey($variantId, $ageGroup, $selectedSize);
                $normalized[$lineKey] = [
                    'variant_id' => $variantId,
                    'age_group' => $ageGroup,
                    'selected_size' => $selectedSize,
                    'quantity' => $qty,
                ];
            }
        }

        return $normalized;
    }

    private function save(array $cart): void
    {
        Session::put(self::KEY, $cart);
    }

    private function makeLineKey(int $variantId, ?string $ageGroup, ?string $selectedSize): string
    {
        $age = $this->normalizeAgeGroup($ageGroup);
        $size = $this->normalizeSelectedSize($selectedSize);
        return $variantId.'|'.($size ?? '').'|'.($age ?? '');
    }

    private function normalizeAgeGroup(?string $ageGroup): ?string
    {
        $ageGroup = trim((string) ($ageGroup ?? ''));
        return $ageGroup === '' ? null : $ageGroup;
    }

    private function normalizeSelectedSize(?string $selectedSize): ?string
    {
        $selectedSize = trim((string) ($selectedSize ?? ''));
        return $selectedSize === '' ? null : $selectedSize;
    }
}
