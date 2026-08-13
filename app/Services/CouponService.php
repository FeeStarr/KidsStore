<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Coupon logic: lookup, eligibility validation, discount calculation and
 * atomic usage tracking. All coupon math is performed server-side here and
 * never trusted from the frontend (Rule 7).
 */
class CouponService
{
    /**
     * Case-insensitive code lookup. Codes are stored lowercase.
     */
    public function findByCode(string $code): ?Coupon
    {
        return Coupon::where('code', $this->normalizeCode($code))->first();
    }

    public function normalizeCode(string $code): string
    {
        return strtolower(trim($code));
    }

    public function create(array $data, array $productIds = [], array $variantIds = []): Coupon
    {
        return DB::transaction(function () use ($data, $productIds, $variantIds) {
            $coupon = Coupon::create($data);
            if (! empty($productIds)) {
                $coupon->products()->sync($productIds);
            }
            if (! empty($variantIds)) {
                $coupon->variants()->sync($variantIds);
            }

            return $coupon->fresh(['products', 'variants']);
        });
    }

    public function update(Coupon $coupon, array $data, array $productIds = [], array $variantIds = []): Coupon
    {
        return DB::transaction(function () use ($coupon, $data, $productIds, $variantIds) {
            $coupon->update($data);
            if (array_key_exists('product_ids', $data)) {
                $coupon->products()->sync($productIds);
            }
            if (array_key_exists('variant_ids', $data)) {
                $coupon->variants()->sync($variantIds);
            }

            return $coupon->fresh(['products', 'variants']);
        });
    }

    public function activate(Coupon $coupon): Coupon
    {
        $coupon->update(['status' => Coupon::STATUS_ACTIVE]);

        return $coupon->fresh();
    }

    public function deactivate(Coupon $coupon): Coupon
    {
        $coupon->update(['status' => Coupon::STATUS_INACTIVE]);

        return $coupon->fresh();
    }

    public function delete(Coupon $coupon): void
    {
        DB::transaction(function () use ($coupon) {
            $coupon->products()->detach();
            $coupon->variants()->detach();
            $coupon->delete();
        });
    }

    public function hasUsageLeft(Coupon $coupon): bool
    {
        return $coupon->usage_limit === null || $coupon->usage_count < $coupon->usage_limit;
    }

    public function customerUsageCount(Coupon $coupon, int $customerId): int
    {
        return (int) CouponUsage::where('coupon_id', $coupon->id)
            ->where('customer_id', $customerId)
            ->count();
    }

    /**
     * Validate a coupon against the cart and customer. Throws on failure.
     *
     * @param  Collection<int, object>  $cartItems  hydrated cart lines (CartService::items())
     */
    public function validate(Coupon $coupon, Collection $cartItems, ?int $customerId): void
    {
        $fail = function (string $message): never {
            throw ValidationException::withMessages(['code' => $message]);
        };

        if ($coupon->status !== Coupon::STATUS_ACTIVE) {
            $fail('This coupon is inactive.');
        }
        if ($coupon->starts_at !== null && $coupon->starts_at > now()) {
            $fail('This coupon is not active yet.');
        }
        if ($coupon->ends_at !== null && $coupon->ends_at <= now()) {
            $fail('This coupon has expired.');
        }
        if (! $this->hasUsageLeft($coupon)) {
            $fail('This coupon has reached its usage limit.');
        }
        if ($customerId !== null && $this->customerUsageCount($coupon, $customerId) >= (int) $coupon->per_customer_limit) {
            $fail('You have already used this coupon.');
        }

        // Minimum order applies to the eligible subtotal (not raw subtotal).
        $eligible = $this->eligibleSubtotal($coupon, $cartItems);
        if ($eligible <= 0) {
            $fail('This coupon does not apply to any of the items in your cart.');
        }
        if ($coupon->minimum_order_amount !== null && $eligible < (float) $coupon->minimum_order_amount) {
            $fail('Your order does not meet the minimum amount for this coupon.');
        }
    }

    /**
     * Sum of line totals for items the coupon applies to.
     * Respects applies_to ("regular-price products only" excludes deal items).
     */
    public function eligibleSubtotal(Coupon $coupon, Collection $cartItems): float
    {
        return (float) $cartItems->where(fn ($line) => $this->itemEligible($coupon, $line))
            ->sum(fn ($line) => (float) $line->line_total);
    }

    /**
     * Whether a single cart line is eligible for the coupon.
     * Targeting: no targets = all products; product targets; variant targets.
     */
    public function itemEligible(Coupon $coupon, object $line): bool
    {
        if ($coupon->applies_to === Coupon::APPLIES_REGULAR_PRICE_ONLY && ! empty($line->deal_id)) {
            return false;
        }

        if ($coupon->variants()->exists()) {
            return $coupon->variants()->whereKey($line->variant->id)->exists();
        }
        if ($coupon->products()->exists()) {
            return $coupon->products()->whereKey($line->product->id)->exists();
        }

        return true;
    }

    /**
     * Authoritative discount amount for the eligible subtotal, capped by
     * maximum_discount_amount. Never negative, never above the subtotal.
     */
    public function discountFor(Coupon $coupon, float $eligibleSubtotal): float
    {
        $discount = $coupon->discountFor(max(0, $eligibleSubtotal));
        if ($coupon->maximum_discount_amount !== null) {
            $discount = min($discount, (float) $coupon->maximum_discount_amount);
        }

        return round(max(0, min($discount, $eligibleSubtotal)), 2);
    }

    /**
     * Atomically record a usage for a customer/order. Throws if the global
     * limit or the per-customer limit has been reached (race-safe).
     *
     * Call only on a successful/confirmed order or its payment verification —
     * NOT merely when a coupon is added to a cart or an unpaid order is made.
     */
    public function recordUsage(Coupon $coupon, int $customerId, int $orderId, float $discountAmount): void
    {
        try {
            DB::transaction(function () use ($coupon, $customerId, $orderId, $discountAmount) {
                // Atomic global-cap guard (row-level, serializes concurrent checkouts).
                $affected = DB::table('coupons')
                    ->where('id', $coupon->id)
                    ->where(fn ($q) => $q->whereNull('usage_limit')->orWhereColumn('usage_count', '<', 'usage_limit'))
                    ->increment('usage_count');

                if ($affected !== 1) {
                    throw new RuntimeException('usage_limit');
                }

                // Per-customer guard is enforced by the DB unique(coupon_id, customer_id).
                try {
                    CouponUsage::create([
                        'coupon_id'       => $coupon->id,
                        'customer_id'     => $customerId,
                        'order_id'        => $orderId,
                        'discount_amount' => round($discountAmount, 2),
                        'used_at'         => now(),
                    ]);
                } catch (\Throwable $e) {
                    // Duplicate per-customer row -> roll back the global increment.
                    throw new RuntimeException('per_customer_limit');
                }
            });
        } catch (\RuntimeException $e) {
            throw new RuntimeException('This coupon is no longer available. Please remove it and try again.');
        }
    }

    /**
     * Release coupon usage for an order (cancellation, expiry, refund).
     */
    public function releaseUsage(Coupon $coupon, int $orderId): void
    {
        DB::transaction(function () use ($coupon, $orderId) {
            $usage = CouponUsage::where('coupon_id', $coupon->id)
                ->where('order_id', $orderId)
                ->get();

            if ($usage->isEmpty()) {
                return;
            }

            CouponUsage::where('coupon_id', $coupon->id)->where('order_id', $orderId)->delete();

            DB::table('coupons')
                ->where('id', $coupon->id)
                ->where('usage_count', '>=', $usage->count())
                ->decrement('usage_count', $usage->count());
        });
    }

    /**
     * Release every coupon attached to an order (used on cancel/expire).
     */
    public function releaseForOrder(Order $order): void
    {
        foreach ($order->items()->whereNotNull('coupon_id')->get() as $item) {
            $coupon = Coupon::find($item->coupon_id);
            if ($coupon) {
                $this->releaseUsage($coupon, $order->id);
            }
        }
    }
}