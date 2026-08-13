<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;

/**
 * Hybrid cart: database-backed for logged-in users, session-backed for guests.
 * On login, the guest session cart is merged into the database cart.
 */
class CartService
{
    private const SESSION_KEY = 'cart';
    private const COUPON_SESSION_KEY = 'cart_coupon';

    public function __construct(private DealService $deals, private CouponService $coupons)
    {
    }

    // ── Public API ────────────────────────────────────────────────────────────

    public function add(int $variantId, int $quantity = 1, ?string $ageGroup = null, ?string $selectedSize = null): void
    {
        if (Auth::check()) {
            $this->dbAdd($variantId, $quantity, $ageGroup, $selectedSize);
        } else {
            $this->sessionAdd($variantId, $quantity, $ageGroup, $selectedSize);
        }
    }

    public function update(int $variantId, int $quantity, ?string $ageGroup = null, ?string $selectedSize = null): void
    {
        $lineKey = $this->makeLineKey($variantId, $ageGroup, $selectedSize);
        $this->updateByLineKey($lineKey, $quantity);
    }

    public function updateByLineKey(string $lineKey, int $quantity): void
    {
        if (Auth::check()) {
            $this->dbUpdateByLineKey($lineKey, $quantity);
        } else {
            $this->sessionUpdateByLineKey($lineKey, $quantity);
        }
        $this->refreshAppliedCoupon();
    }

    public function remove(int $variantId, ?string $ageGroup = null, ?string $selectedSize = null): void
    {
        $lineKey = $this->makeLineKey($variantId, $ageGroup, $selectedSize);
        $this->removeByLineKey($lineKey);
    }

    public function removeByLineKey(string $lineKey): void
    {
        if (Auth::check()) {
            $this->dbRemoveByLineKey($lineKey);
        } else {
            $this->sessionRemoveByLineKey($lineKey);
        }
        $this->refreshAppliedCoupon();
    }

    public function clear(): void
    {
        if (Auth::check()) {
            $this->dbClear();
        } else {
            Session::forget(self::SESSION_KEY);
        }
        $this->setCouponId(null);
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

        $lines = collect($raw)->map(function (array $line, string $lineKey) use ($variants) {
            $variantId = (int) ($line['variant_id'] ?? 0);
            $qty = (int) ($line['quantity'] ?? 0);
            $selectedAgeGroup = $line['age_group'] ?? null;
            $selectedSize = $line['selected_size'] ?? null;

            $variant = $variants->get($variantId);
            if (! $variant) {
                return null;
            }

            $pricing    = $this->deals->priceForVariant($variant);
            $unit       = (float) $pricing['unit_price'];
            $discount   = (float) $pricing['discount'];
            $netUnit    = (float) $pricing['net_unit'];
            $lineTotal  = $netUnit * $qty;

            return (object) [
                'line_key'   => $lineKey,
                'variant'    => $variant,
                'product'    => $variant->product,
                'quantity'   => $qty,
                'age_group'  => $selectedAgeGroup,
                'selected_size' => $selectedSize,
                'unit_price' => $unit,
                'original_unit_price' => (float) $pricing['original_unit_price'],
                'discount'   => $discount,
                'discount_amount' => (float) $pricing['discount_amount'],
                'net_unit'   => $netUnit,
                'line_total' => $lineTotal,
                'deal_id'    => $pricing['deal_id'],
                'deal'       => $pricing['deal'],
            ];
        })->filter()->values();

        // Drop the applied coupon if items/eligibility changed and it is no longer valid.
        if ($this->couponId() !== null) {
            $coupon = Coupon::find($this->couponId());
            if ($coupon) {
                try {
                    $this->coupons->validate($coupon, $lines, Auth::id());
                } catch (ValidationException $e) {
                    $this->setCouponId(null);
                }
            } else {
                $this->setCouponId(null);
            }
        }

        return $lines;
    }

    public function subtotal(): float
    {
        return (float) $this->items()->sum('line_total');
    }

    // ── Coupons ──────────────────────────────────────────────────────────────

    /**
     * Validate and attach a coupon to the cart. Throws ValidationException on failure.
     */
    public function applyCoupon(string $code): Coupon
    {
        $coupon = $this->coupons->findByCode($code);

        if (! $coupon) {
            throw ValidationException::withMessages(['code' => 'That coupon code is invalid.']);
        }

        $this->coupons->validate($coupon, $this->items(), Auth::id());

        $this->setCouponId($coupon->id);

        return $coupon->fresh();
    }

    public function removeCoupon(): void
    {
        $this->setCouponId(null);
    }

    public function couponId(): ?int
    {
        if (Auth::check()) {
            $id = Cart::where('user_id', Auth::id())->value('coupon_id');
            return $id ? (int) $id : null;
        }

        $id = Session::get(self::COUPON_SESSION_KEY);
        return $id ? (int) $id : null;
    }

    public function coupon(): ?Coupon
    {
        $id = $this->couponId();
        return $id ? Coupon::find($id) : null;
    }

    /**
     * Authoritative coupon discount for the current cart contents.
     */
    public function couponDiscount(): float
    {
        $coupon = $this->coupon();
        if (! $coupon) {
            return 0.0;
        }

        $items = $this->items();

        return $this->coupons->discountFor(
            $coupon,
            $this->coupons->eligibleSubtotal($coupon, $items)
        );
    }

    // ── Guest → Authenticated merge ──────────────────────────────────────────

    /**
     * Merge session cart into the database cart. Called after login/register.
     */
    public function mergeSessionIntoDatabase(): void
    {
        $sessionCart = Session::get(self::SESSION_KEY, []);
        if (empty($sessionCart)) {
            return;
        }

        $cart = Cart::firstOrCreate(['user_id' => Auth::id()]);

        // Carry the guest session coupon into the persisted cart.
        $sessionCouponId = Session::get(self::COUPON_SESSION_KEY);
        if ($sessionCouponId && $cart->coupon_id === null) {
            $cart->update(['coupon_id' => (int) $sessionCouponId]);
        }
        Session::forget(self::COUPON_SESSION_KEY);

        foreach ($sessionCart as $line) {
            $variantId = (int) ($line['variant_id'] ?? 0);
            $ageGroup = $this->normalizeAgeGroup($line['age_group'] ?? null);
            $selectedSize = $this->normalizeSelectedSize($line['selected_size'] ?? null);
            $qty = max(1, (int) ($line['quantity'] ?? 0));

            if ($variantId <= 0) continue;

            $existing = $cart->items()
                ->where('product_variant_id', $variantId)
                ->where('age_group', $ageGroup)
                ->where('selected_size', $selectedSize)
                ->first();

            if ($existing) {
                $existing->update(['quantity' => $existing->quantity + $qty]);
            } else {
                $cart->items()->create([
                    'product_variant_id' => $variantId,
                    'age_group'          => $ageGroup,
                    'selected_size'      => $selectedSize,
                    'quantity'           => $qty,
                ]);
            }
        }

        Session::forget(self::SESSION_KEY);
    }

    // ── Database operations ───────────────────────────────────────────────────

    private function dbAdd(int $variantId, int $quantity, ?string $ageGroup, ?string $selectedSize): void
    {
        $cart = Cart::firstOrCreate(['user_id' => Auth::id()]);
        $ageGroup = $this->normalizeAgeGroup($ageGroup);
        $selectedSize = $this->normalizeSelectedSize($selectedSize);

        $existing = $cart->items()
            ->where('product_variant_id', $variantId)
            ->where('age_group', $ageGroup)
            ->where('selected_size', $selectedSize)
            ->first();

        if ($existing) {
            $existing->update(['quantity' => $existing->quantity + max(1, $quantity)]);
        } else {
            $cart->items()->create([
                'product_variant_id' => $variantId,
                'age_group'          => $ageGroup,
                'selected_size'      => $selectedSize,
                'quantity'           => max(1, $quantity),
            ]);
        }
    }

    private function dbUpdateByLineKey(string $lineKey, int $quantity): void
    {
        $cart = Cart::where('user_id', Auth::id())->first();
        if (! $cart) return;

        $parts = $this->parseLineKey($lineKey);
        $item = $cart->items()
            ->where('product_variant_id', $parts['variant_id'])
            ->where('age_group', $parts['age_group'])
            ->where('selected_size', $parts['selected_size'])
            ->first();

        if (! $item) return;

        if ($quantity <= 0) {
            $item->delete();
        } else {
            $item->update(['quantity' => $quantity]);
        }
    }

    private function dbRemoveByLineKey(string $lineKey): void
    {
        $cart = Cart::where('user_id', Auth::id())->first();
        if (! $cart) return;

        $parts = $this->parseLineKey($lineKey);
        $cart->items()
            ->where('product_variant_id', $parts['variant_id'])
            ->where('age_group', $parts['age_group'])
            ->where('selected_size', $parts['selected_size'])
            ->delete();
    }

    private function dbClear(): void
    {
        $cart = Cart::where('user_id', Auth::id())->first();
        if ($cart) {
            $cart->items()->delete();
        }
    }

    private function dbRaw(): array
    {
        $cart = Cart::where('user_id', Auth::id())->first();
        if (! $cart) return [];

        $normalized = [];
        foreach ($cart->items()->get() as $item) {
            $lineKey = $this->makeLineKey(
                $item->product_variant_id,
                $item->age_group,
                $item->selected_size
            );
            $normalized[$lineKey] = [
                'variant_id'    => $item->product_variant_id,
                'age_group'     => $item->age_group,
                'selected_size' => $item->selected_size,
                'quantity'      => $item->quantity,
            ];
        }
        return $normalized;
    }

    // ── Session operations (guest) ────────────────────────────────────────────

    private function sessionAdd(int $variantId, int $quantity, ?string $ageGroup, ?string $selectedSize): void
    {
        $cart = $this->sessionRaw();
        $lineKey = $this->makeLineKey($variantId, $ageGroup, $selectedSize);
        $entry = $cart[$lineKey] ?? [
            'variant_id' => $variantId,
            'age_group' => $ageGroup,
            'selected_size' => $selectedSize,
            'quantity' => 0,
        ];
        $entry['quantity'] = (int) ($entry['quantity'] ?? 0) + max(1, $quantity);
        $cart[$lineKey] = $entry;
        Session::put(self::SESSION_KEY, $cart);
    }

    private function sessionUpdateByLineKey(string $lineKey, int $quantity): void
    {
        $cart = $this->sessionRaw();
        if ($quantity <= 0) {
            unset($cart[$lineKey]);
        } else {
            if (! isset($cart[$lineKey])) return;
            $cart[$lineKey]['quantity'] = $quantity;
        }
        Session::put(self::SESSION_KEY, $cart);
    }

    private function sessionRemoveByLineKey(string $lineKey): void
    {
        $cart = $this->sessionRaw();
        unset($cart[$lineKey]);
        Session::put(self::SESSION_KEY, $cart);
    }

    private function sessionRaw(): array
    {
        $raw = (array) Session::get(self::SESSION_KEY, []);
        $normalized = [];

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
                if ($variantId <= 0) continue;
                $ageGroup = $this->normalizeAgeGroup($value['age_group'] ?? null);
                $selectedSize = $this->normalizeSelectedSize($value['selected_size'] ?? null);
                $qty = max(0, (int) ($value['quantity'] ?? 0));
                if ($qty <= 0) continue;
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

    // ── Shared helpers ────────────────────────────────────────────────────────

    /**
     * Re-run coupon validation after a cart mutation; drop it if no longer valid.
     */
    private function refreshAppliedCoupon(): void
    {
        $couponId = $this->couponId();
        if ($couponId === null) {
            return;
        }

        $coupon = Coupon::find($couponId);
        if (! $coupon) {
            $this->setCouponId(null);
            return;
        }

        try {
            $this->coupons->validate($coupon, $this->items(), Auth::id());
        } catch (ValidationException $e) {
            $this->setCouponId(null);
        }
    }

    private function setCouponId(?int $couponId): void
    {
        if (Auth::check()) {
            $cart = Cart::firstOrCreate(['user_id' => Auth::id()]);
            $cart->update(['coupon_id' => $couponId]);
        } elseif ($couponId === null) {
            Session::forget(self::COUPON_SESSION_KEY);
        } else {
            Session::put(self::COUPON_SESSION_KEY, $couponId);
        }
    }

    private function raw(): array
    {
        if (Auth::check()) {
            return $this->dbRaw();
        }
        return $this->sessionRaw();
    }

    private function makeLineKey(int $variantId, ?string $ageGroup, ?string $selectedSize): string
    {
        $age = $this->normalizeAgeGroup($ageGroup);
        $size = $this->normalizeSelectedSize($selectedSize);
        return $variantId.'|'.($size ?? '').'|'.($age ?? '');
    }

    private function parseLineKey(string $lineKey): array
    {
        $parts = explode('|', $lineKey);
        return [
            'variant_id'    => (int) ($parts[0] ?? 0),
            'selected_size' => $parts[1] !== '' ? $parts[1] : null,
            'age_group'     => $parts[2] !== '' ? $parts[2] : null,
        ];
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
