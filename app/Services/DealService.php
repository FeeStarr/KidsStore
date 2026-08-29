<?php

namespace App\Services;

use App\Models\Deal;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Central deal logic: lifecycle, pricing rules, overlap prevention.
 * The deal price is ALWAYS computed server-side here - never trusted from input.
 */
class DealService
{
    /** Per-request cache of the single live deal per product id. */
    private array $activeDealCache = [];

    public function create(array $data, array $productIds, array $variantIds = []): Deal
    {
        $this->assertNoOverlap($productIds, $data['starts_at'] ?? null, $data['ends_at'] ?? null, null, $variantIds);

        return DB::transaction(function () use ($data, $productIds, $variantIds) {
            $deal = Deal::create($data);
            if (! empty($productIds)) {
                $deal->products()->sync($productIds);
            }
            if (! empty($variantIds)) {
                $deal->variants()->sync($variantIds);
            }

            return $deal->fresh(['products', 'variants']);
        });
    }

    public function update(Deal $deal, array $data, array $productIds, array $variantIds = []): Deal
    {
        $this->assertNoOverlap(
            $productIds,
            $data['starts_at'] ?? $deal->starts_at,
            $data['ends_at'] ?? $deal->ends_at,
            $deal->id,
            $variantIds
        );

        return DB::transaction(function () use ($deal, $data, $productIds, $variantIds) {
            $deal->update($data);
            $deal->products()->sync($productIds);
            $deal->variants()->sync($variantIds);

            return $deal->fresh(['products', 'variants']);
        });
    }

    public function cancel(Deal $deal): Deal
    {
        $deal->update(['status' => Deal::STATUS_CANCELLED]);

        return $deal->fresh('products');
    }

    public function duplicate(Deal $deal): Deal
    {
        $productIds = $deal->products()->pluck('products.id')->all();

        return DB::transaction(function () use ($deal, $productIds) {
            $copy = Deal::create([
                'title'          => $deal->title.' (Copy)',
                'slug'           => $deal->slug.'-'.strtolower(substr(bin2hex(random_bytes(3)), 0, 6)),
                'description'    => $deal->description,
                'discount_type'  => $deal->discount_type,
                'discount_value' => $deal->discount_value,
                'starts_at'      => $deal->starts_at,
                'ends_at'        => $deal->ends_at,
                'status'         => Deal::STATUS_DRAFT,
                'banner_image'   => $deal->banner_image,
                'thumbnail_image'=> $deal->thumbnail_image,
                'is_featured'    => $deal->is_featured,
                'max_uses'       => $deal->max_uses,
                'current_uses'   => 0,
                'created_by'     => auth()->id(),
            ]);
            $copy->products()->sync($productIds);

            return $copy->fresh('products');
        });
    }

    public function delete(Deal $deal): void
    {
        DB::transaction(function () use ($deal) {
            $deal->products()->detach();
            $deal->delete();
        });
    }

    /**
     * The single live deal currently applying to a product (product-level deals).
     */
    public function activeDealForProduct(int|Product $product): ?Deal
    {
        $id = $product instanceof Product ? $product->id : (int) $product;
        if (array_key_exists($id, $this->activeDealCache)) {
            return $this->activeDealCache[$id];
        }

        return $this->activeDealCache[$id] = Deal::live()
            ->whereHas('products', fn ($q) => $q->whereKey($id))
            ->where($this->usageLeftClosure())
            ->first();
    }

    /**
     * A deal is shown/applied only while it has remaining uses (or is unlimited).
     */
    private function usageLeftClosure(): callable
    {
        return fn ($q) => $q->whereNull('max_uses')->orWhereColumn('current_uses', '<', 'max_uses');
    }

    public function activeDealForVariant(ProductVariant $variant): ?Deal
    {
        $key = "variant:{$variant->id}";
        if (array_key_exists($key, $this->activeDealCache)) {
            return $this->activeDealCache[$key];
        }

        // Precedence (Rule 8): a variant-level deal wins over a product-level deal.
        $deal = Deal::live()
            ->whereHas('variants', fn ($q) => $q->whereKey($variant->id))
            ->where($this->usageLeftClosure())
            ->first();

        if (! $deal) {
            $deal = $this->activeDealForProduct($variant->product_id);
        }

        return $this->activeDealCache[$key] = $deal;
    }

    public function hasUsageLeft(Deal $deal): bool
    {
        return $deal->max_uses === null || $deal->current_uses < $deal->max_uses;
    }

    /**
     * Atomically increment usage under the cap. Returns false if the deal is
     * exhausted. Call inside the order transaction to keep counting race-safe.
     */
    public function consumeUsage(int|Deal $deal, int $by = 1): bool
    {
        $id = $deal instanceof Deal ? $deal->id : (int) $deal;
        $affected = DB::table('deals')
            ->where('id', $id)
            ->where(fn ($q) => $q->whereNull('max_uses')->orWhereColumn('current_uses', '<', 'max_uses'))
            ->increment('current_uses', $by);

        return $affected === 1;
    }

    /**
     * Release previously consumed usage (never below zero).
     */
    public function releaseUsage(int|Deal $deal, int $by = 1): void
    {
        $id = $deal instanceof Deal ? $deal->id : (int) $deal;
        DB::table('deals')
            ->where('id', $id)
            ->where('current_uses', '>=', $by)
            ->decrement('current_uses', $by);
    }

    /**
     * Full server-side pricing breakdown for a variant.
     * Deal replaces the static discount when a deal is live.
     */
    public function priceForVariant(ProductVariant $variant): array
    {
        $base = (float) $variant->selling_price;
        $deal = $this->activeDealForVariant($variant);

        if ($deal) {
            $net = $deal->priceFor($base);

            return [
                'base_price'          => $base,
                'unit_price'          => $base,
                'net_unit'            => $net,
                'discount'            => 0.0,
                'original_unit_price' => $base,
                'discount_amount'     => $deal->discountFor($base),
                'deal_id'             => $deal->id,
                'deal'                => $deal,
            ];
        }

        $discount = (float) $variant->effective_discount;
        $net      = $base * (1 - $discount / 100);

        return [
            'base_price'          => $base,
            'unit_price'          => $base,
            'net_unit'            => round($net, 2),
            'discount'            => $discount,
            'original_unit_price' => $base,
            'discount_amount'     => round($base - $net, 2),
            'deal_id'             => null,
            'deal'                => null,
        ];
    }

    /**
     * Rule 4: prevent overlapping active/scheduled deals for the same products.
     */
    public function assertNoOverlap(array $productIds, ?string $startsAt, ?string $endsAt, ?int $ignoreDealId = null, array $variantIds = []): void
    {
        if (empty($productIds) && empty($variantIds)) {
            return;
        }

        $start = $startsAt ?: now()->toDateTimeString();
        $end   = $endsAt;

        $conflict = Deal::where(function ($q) use ($productIds, $variantIds) {
            if (! empty($productIds)) {
                $q->orWhereHas('products', fn ($inner) => $inner->whereIn('products.id', $productIds));
            }
            if (! empty($variantIds)) {
                $q->orWhereHas('variants', fn ($inner) => $inner->whereIn('product_variants.id', $variantIds));
            }
        })
            ->whereNotIn('status', [Deal::STATUS_DRAFT, Deal::STATUS_CANCELLED])
            ->when($ignoreDealId, fn ($q) => $q->whereKeyNot($ignoreDealId))
            ->where(function ($q) use ($start, $end) {
                // Overlap: other.starts_at <= (new.end OR forever) AND other.ends_at >= new.start
                $q->where(function ($inner) use ($start, $end) {
                    if ($end === null) {
                        // New deal runs forever; any started deal conflicts.
                        $inner->whereNull('starts_at')->orWhere('starts_at', '<=', $start);
                    } else {
                        $inner->whereNull('starts_at')->orWhere('starts_at', '<=', $end);
                    }
                })->where(function ($inner) use ($start) {
                    $inner->whereNull('ends_at')->orWhere('ends_at', '>', $start);
                });
            })
            ->exists();

        if ($conflict) {
            $label = empty($productIds) ? 'variants' : 'products';
            throw ValidationException::withMessages([
                'product_ids' => "One or more selected {$label} already has an active or scheduled deal during this period.",
            ]);
        }
    }

    /**
     * Persist clock-derived statuses for every deal (scheduled -> active -> expired).
     */
    public function syncStatuses(): int
    {
        $now = now();
        $updated = 0;

        // Activate scheduled deals whose start has passed.
        $updated += Deal::where('status', Deal::STATUS_SCHEDULED)
            ->where('starts_at', '<=', $now)
            ->update(['status' => Deal::STATUS_ACTIVE]);

        // Expire active deals whose end has passed.
        $updated += Deal::where('status', Deal::STATUS_ACTIVE)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', $now)
            ->update(['status' => Deal::STATUS_EXPIRED]);

        return $updated;
    }
}
