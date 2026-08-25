<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Notifications\NotificationRecipients;
use App\Notifications\OrderPlacedNotification;
use App\Notifications\OrderStatusNotification;
use App\Services\Contracts\InventoryServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

/**
 * Encapsulates order business logic. Stock is decreased through InventoryService.
 */
class OrderService
{
    public function __construct(
        private InventoryServiceInterface $inventory,
        private DealService $deals,
        private CouponService $coupons,
    ) {
    }

    /**
     * @param array{
     *   reference?: string,
     *   customer_id?: int|null,
     *   order_date: string,
     *   status?: string,
     *   discount?: float,
     *   shipping_fee?: float,
     *   note?: string|null,
     *   items: array<int, array{product_id:int,quantity:int,unit_price?:float,discount?:float}>
     * } $data
     */
    public function create(array $data): Order
    {
        $order = DB::transaction(function () use ($data) {
            $order = Order::create([
                'reference'              => $data['reference'] ?? $this->generateReference(),
                'customer_id'            => $data['customer_id'] ?? null,
                'order_date'             => $data['order_date'],
                'status'                 => $data['status'] ?? 'confirmed',
                'delivery_method'        => $data['delivery_method'] ?? 'delivery',
                'payment_method'         => $data['payment_method'] ?? null,
                'pickup_station_id'      => $data['pickup_station_id'] ?? null,
                'delivery_address'       => $data['delivery_address'] ?? null,
                'discount'               => (float) ($data['discount'] ?? 0),
                'shipping_fee'           => (float) ($data['shipping_fee'] ?? 0),
                'note'                   => $data['note'] ?? null,
                'expected_delivery_date' => $data['expected_delivery_date']
                    ?? now()->parse($data['order_date'])->addDays(7)->toDateString(),
            ]);

            foreach ($data['items'] as $row) {
                $this->createItem($order, $row);
            }

            // Consume deal usage once per distinct deal applied on this order.
            // Throwing rolls back the whole order and surfaces a clear error.
            foreach ($order->items()->whereNotNull('deal_id')->pluck('deal_id')->unique() as $dealId) {
                if (! $this->deals->consumeUsage((int) $dealId)) {
                    throw new RuntimeException('One of the deals in your cart has reached its usage limit and is no longer available.');
                }
            }

            // Revalidate the coupon server-side and apportion the discount across
            // eligible items. The client-supplied coupon_discount is never trusted.
            $this->applyCouponToOrder($order, $data);

            $this->recalculateTotals($order);

            // Reserve stock for every order at placement. For pending-payment
            // (bank transfer) orders this prevents overselling: the units are
            // held as a reservation and released on expiry/cancellation.
            $this->applyInventoryDecrease($order);

            return $order->fresh('items.product');
        });

        // Orders created straight through to confirmed (pay-at-pickup, pay-on-
        // delivery, admin-created) never pass through confirm(), so the
        // "order placed" email fires here. Pending-payment orders are notified
        // when their payment is verified later in confirm().
        if (in_array($order->status, ['confirmed', 'processing', 'shipping to station', 'out for delivery', 'ready for pick up', 'delivered'], true)) {
            $this->notifyOrderPlaced($order);
        }

        return $order;
    }

    /**
     * Confirm an order placed: decrease inventory.
     */
    public function confirm(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            if (in_array($order->status, ['cancelled', 'expired'], true)) {
                throw new RuntimeException('Cannot confirm a cancelled or expired order.');
            }

            if (in_array($order->status, ['confirmed', 'processing', 'shipping to station', 'out for delivery', 'ready for pick up', 'delivered'], true)) {
                return $order;
            }

            $prev = $order->status;
            $this->applyInventoryDecrease($order);
            $order->update(['status' => 'confirmed', 'confirmed_at' => now()]);

            // Count coupon usage only once payment is verified (confirmed).
            $this->recordCouponUsageIfAny($order->fresh());

            $this->notifyStatusChange($order->fresh(), $prev);

            // Send order placed notification (skip for custom orders — they have their own notifications)
            if (!$order->custom_order_id) {
                $this->notifyOrderPlaced($order->fresh());
            }

            return $order->fresh();
        });
    }

    public function markPendingConfirmation(Order $order): Order
    {
        if (! in_array($order->status, ['ordered'], true)) {
            throw new RuntimeException('Order cannot be moved to pending confirmation from its current status.');
        }
        $prev = $order->status;
        $order->update(['status' => 'pending confirmation']);
        $this->notifyStatusChange($order->fresh(), $prev);
        return $order->fresh();
    }

    public function markProcessing(Order $order): Order
    {
        if (in_array($order->status, ['ordered', 'pending confirmation'], true)) {
            $this->confirm($order);
        }
        $prev = $order->status;
        $order->update(['status' => 'processing', 'processing_at' => now()]);
        $this->notifyStatusChange($order->fresh(), $prev);
        return $order->fresh();
    }

    public function markShipped(Order $order): Order
    {
        if (in_array($order->status, ['ordered', 'pending confirmation'], true)) {
            $this->confirm($order);
        }
        $prev = $order->status;
        $order->update(['status' => 'out for delivery', 'shipped_at' => now()]);
        $this->notifyStatusChange($order->fresh(), $prev);
        return $order->fresh();
    }

    /**
     * Admin marks order as shipping to pickup station.
     */
    public function markShippingToStation(Order $order): Order
    {
        if (in_array($order->status, ['ordered', 'pending confirmation'], true)) {
            $this->confirm($order);
        }
        $prev = $order->status;
        $order->update(['status' => 'shipping to station', 'shipped_at' => now()]);
        $this->notifyStatusChange($order->fresh(), $prev);
        return $order->fresh();
    }

    /**
     * Pickup station marks order as ready for customer pickup.
     */
    public function markReadyForPickup(Order $order): Order
    {
        $prev = $order->status;
        $order->update(['status' => 'ready for pick up', 'ready_for_pickup_at' => now()]);
        $this->notifyStatusChange($order->fresh(), $prev);
        return $order->fresh();
    }

    public function markDelivered(Order $order): Order
    {
        if (in_array($order->status, ['ordered', 'pending confirmation'], true)) {
            $this->confirm($order);
        }
        $prev = $order->status;
        $order->update(['status' => 'delivered', 'delivered_at' => now()]);
        $this->notifyStatusChange($order->fresh(), $prev);
        return $order->fresh();
    }

    /**
     * Mark order as pickup window expired (after 4-day collection window).
     */
    public function markPickupWindowExpired(Order $order): Order
    {
        $prev = $order->status;
        $order->update(['status' => 'pickup window expired']);

        // Mark remaining unpicked items as expired too
        $order->items()
            ->where('pickup_status', 'ready for pickup')
            ->update(['pickup_status' => 'expired']);

        $this->notifyStatusChange($order->fresh(), $prev);
        return $order->fresh();
    }

    /**
     * Cancel an order. If inventory was decreased, restore it.
     */
    public function cancel(Order $order): Order
    {
        $result = DB::transaction(function () use ($order) {
            $prev = $order->status;

            if (! in_array($order->status, ['cancelled', 'expired'], true)) {
                $this->inventory->reverseMovementsFor(Order::class, $order->id, 'Order cancelled');
                $this->releaseDealUsage($order);
                $this->coupons->releaseForOrder($order);
            }
            $order->update(['status' => 'cancelled', 'cancelled_at' => now()]);

            return [$order->fresh(), $prev];
        });

        $this->notifyStatusChange($result[0], $result[1]);

        return $result[0];
    }

    public function recordPayment(Order $order, float $amount): Order
    {
        return DB::transaction(function () use ($order, $amount) {
            // Lock the order row to prevent concurrent payment race conditions
            $order = $order->lockForUpdate()->first();

            $paid = round(((float) $order->amount_paid) + $amount, 2);
            $order->update([
                'amount_paid' => $paid,
                'payment_status' => $this->resolvePaymentStatus($order->forceFill(['amount_paid' => $paid])),
            ]);

            return $order;
        });
    }

    public function generateReference(): string
    {
        $next = (Order::max('id') ?? 0) + 1;

        return 'ORD-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    private function createItem(Order $order, array $row): OrderItem
    {
        $variant   = $this->resolveVariant($row);
        $product   = $variant->product;

        $quantity = (int) $row['quantity'];

        // A deal reference is revalidated server-side here: only a LIVE deal
        // may affect pricing (Rule 1). The cart/checkout price is not trusted.
        $dealId = isset($row['deal_id']) && (int) $row['deal_id'] > 0 ? (int) $row['deal_id'] : null;
        $deal   = $dealId ? \App\Models\Deal::find($dealId) : null;

        if ($deal && $deal->is_live && $this->deals->hasUsageLeft($deal)) {
            // Base price is re-read from the variant, never from the client.
            $base              = (float) ($variant->selling_price ?: $product->selling_price);
            $unitPrice         = $deal->priceFor($base);
            $originalUnitPrice = $base;
            $discountAmount    = $deal->discountFor($base);
            $discount          = 0.0;
            $lineTotal         = $unitPrice * $quantity;
        } else {
            $unitPrice         = (float) ($row['unit_price'] ?? $variant->selling_price ?: $product->selling_price);
            $discount          = (float) ($row['discount'] ?? 0);
            $discountAmount    = $unitPrice * ($discount / 100);
            $lineTotal         = ($unitPrice - $discountAmount) * $quantity;
            $originalUnitPrice = $unitPrice;
            $dealId            = null;
        }

        $landedUnitCost = $this->resolveLandedUnitCost($variant->id);
        // Calculate pickup station fee for this item (if order is pickup)
        $pickupFeePct = 0.0;
        if ($order->delivery_method === Order::DELIVERY_METHOD_PICKUP && $order->pickup_station_id) {
            $station = $order->pickupStation()->first();
            $pickupFeePct = $station?->fee_pct ? (float) $station->fee_pct : 0.0;
        }

        $pickupStationFee = round($lineTotal * ($pickupFeePct / 100), 2);

        return $order->items()->create([
            'product_id'         => $product->id,
            'product_variant_id' => $variant->id,
            'selected_age_group' => $variant->ageRange?->name ?? ($row['selected_age_group'] ?? null),
            'selected_size'      => $variant->sizeRef?->name ?? ($row['selected_size'] ?? null),
            'quantity'           => $quantity,
            'unit_price'         => $unitPrice,
            'original_unit_price'=> $originalUnitPrice,
            'landed_unit_cost'   => $landedUnitCost,
            'discount'           => $discount,
            'discount_amount'    => $discountAmount,
            'deal_id'            => $dealId,
            'coupon_id'          => isset($row['coupon_id']) && (int) $row['coupon_id'] > 0 ? (int) $row['coupon_id'] : null,
            'coupon_discount'    => round((float) ($row['coupon_discount'] ?? 0), 2),
            'line_total'         => $lineTotal,
            'pickup_station_fee' => $pickupStationFee,
        ]);
    }

    /**
     * Return deal usage for every deal applied on this order (e.g. on cancel).
     */
    private function releaseDealUsage(Order $order): void
    {
        foreach ($order->items()->whereNotNull('deal_id')->pluck('deal_id')->unique() as $dealId) {
            $this->deals->releaseUsage((int) $dealId);
        }
    }

    /**
     * Authoritative coupon handling at order time. Revalidates the coupon
     * against the real order rows, apportions the discount across eligible
     * items and counts usage once the order is confirmed (payment verified).
     * Throwing here rolls the whole order back.
     */
    private function applyCouponToOrder(Order $order, array $data): void
    {
        $couponId = isset($data['coupon_id']) && (int) $data['coupon_id'] > 0
            ? (int) $data['coupon_id']
            : null;

        // Fall back to a coupon_id carried on individual item rows.
        if ($couponId === null) {
            $couponId = (int) $order->items()->whereNotNull('coupon_id')->value('coupon_id') ?: null;
        }

        if ($couponId === null) {
            return;
        }

        $coupon = \App\Models\Coupon::find($couponId);

        if (! $coupon) {
            $this->dropCouponFromOrder($order);
            return;
        }

        $lines = $this->orderLines($order);

        try {
            $this->coupons->validate($coupon, $lines, (int) $order->customer_id);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw new RuntimeException('This coupon is no longer valid. Please remove it and try again.');
        }

        $eligible     = $this->coupons->eligibleSubtotal($coupon, $lines);
        $totalDiscount = $this->coupons->discountFor($coupon, $eligible);

        if ($totalDiscount > 0) {
            foreach ($order->items()->with('variant.product')->get() as $item) {
                if (! $this->coupons->itemEligible($coupon, $this->itemLine($item))) {
                    continue;
                }
                $share = $eligible > 0 ? (float) $item->line_total / $eligible : 0;
                $item->update([
                    'coupon_id'       => $coupon->id,
                    'coupon_discount' => round($totalDiscount * $share, 2),
                ]);
            }
        }

        // Only count usage on an order that is already confirmed at placement.
        if ($order->status !== 'pending payment' && (int) $order->customer_id > 0) {
            $this->recordCouponUsageIfAny($order);
        }
    }

    /**
     * Record coupon usage for an order exactly once (race-safe). Failure to
     * record (e.g. limit raced away while awaiting payment) is logged rather
     * than blocking the confirmation.
     */
    private function recordCouponUsageIfAny(Order $order): void
    {
        $couponId = (int) $order->items()->whereNotNull('coupon_id')->value('coupon_id');

        if ($couponId === 0 || (int) $order->customer_id <= 0) {
            return;
        }

        if (\App\Models\CouponUsage::where('order_id', $order->id)->exists()) {
            return;
        }

        $coupon = \App\Models\Coupon::find($couponId);
        $totalDiscount = (float) $order->items()->where('coupon_id', $couponId)->sum('coupon_discount');

        if (! $coupon) {
            return;
        }

        try {
            $this->coupons->recordUsage($coupon, (int) $order->customer_id, $order->id, $totalDiscount);
        } catch (\RuntimeException $e) {
            \Illuminate\Support\Facades\Log::warning('Coupon usage could not be recorded at order confirmation', [
                'order'   => $order->reference,
                'coupon'  => $coupon->code,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Clear a coupon that no longer exists from the order's items.
     */
    private function dropCouponFromOrder(Order $order): void
    {
        $order->items()->update(['coupon_id' => null, 'coupon_discount' => 0]);
    }

    /**
     * Hydrate order rows into coupon-compatible line objects.
     */
    private function orderLines(Order $order): \Illuminate\Support\Collection
    {
        return $order->items()->with('variant.product')->get()->map(fn ($item) => $this->itemLine($item));
    }

    private function itemLine(\App\Models\OrderItem $item): object
    {
        return (object) [
            'variant'    => $item->variant,
            'product'    => $item->variant?->product,
            'deal_id'    => $item->deal_id,
            'line_total' => (float) $item->line_total,
        ];
    }

    private function resolveVariant(array $row): ProductVariant
    {
        if (! empty($row['product_variant_id'])) {
            return ProductVariant::with('product')->findOrFail($row['product_variant_id']);
        }
        $product = Product::with('defaultVariant')->findOrFail($row['product_id']);
        $variant = $product->defaultVariant;
        if (! $variant) {
            throw new RuntimeException("Product '{$product->name}' has no variants.");
        }
        $variant->setRelation('product', $product);
        return $variant;
    }

    /**
     * Latest landed unit cost for this variant from its most recent received purchase.
     */
    private function resolveLandedUnitCost(int $variantId): float
    {
        $row = DB::table('purchase_items')
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->where('purchase_items.product_variant_id', $variantId)
            ->where('purchases.status', 'received')
            ->orderByDesc('purchases.purchase_date')
            ->orderByDesc('purchase_items.id')
            ->select('purchase_items.cost_price', 'purchase_items.shipping_fee',
                'purchase_items.packaging_cost', 'purchase_items.other_costs',
                'purchase_items.discount')
            ->first();

        if (! $row) {
            return 0.0;
        }

        $unitCost = (float) $row->cost_price + (float) $row->shipping_fee
                  + (float) $row->packaging_cost + (float) $row->other_costs;

        return round($unitCost * (1 - ((float) $row->discount / 100)), 2);
    }

    private function recalculateTotals(Order $order): void
    {
        $subtotal = (float) $order->items()->sum('line_total');
        // Order-level discount is also a PERCENTAGE (0-100) of the subtotal.
        $orderDiscount = $subtotal * ((float) $order->discount / 100);
        // Coupon discount is an absolute amount apportioned across eligible items.
        $couponDiscount = (float) $order->items()->sum('coupon_discount');
        
        // Shipping is a flat per-order fee.
        $totalShippingBeforeDiscount = (float) $order->shipping_fee;
        
        // Apply shipping discount from site settings
        $shippingDiscountPct = (float) \App\Models\Setting::get('shipping_discount', 0);
        $shippingDiscountAmount = $totalShippingBeforeDiscount * ($shippingDiscountPct / 100);
        $totalShipping = $totalShippingBeforeDiscount - $shippingDiscountAmount;
        
        $grand = $subtotal - $orderDiscount - $couponDiscount + $totalShipping;

        $pickupTotal = (float) $order->items()->sum('pickup_station_fee');

        $order->update([
            'subtotal'    => $subtotal,
            'grand_total' => max(0, $grand),
            'total_amount' => max(0, $grand),
            'pickup_station_fee_total' => $pickupTotal,
        ]);
    }

    private function applyInventoryDecrease(Order $order): void
    {
        foreach ($order->items()->with('variant.product')->get() as $item) {
            $variant = $item->variant;
            if (! $variant) {
                continue;
            }

            // Idempotent: reserve each variant once per order. Stock was already
            // decreased either at placement or by a previous confirm/re-run.
            $alreadyReserved = InventoryMovement::where('reference_type', Order::class)
                ->where('reference_id', $order->id)
                ->where('product_variant_id', $variant->id)
                ->where('quantity', '<', 0)
                ->exists();

            if ($alreadyReserved) {
                continue;
            }

            $this->inventory->decreaseFromOrder(
                $variant,
                $item->quantity,
                Order::class,
                $order->id,
                "Order #{$order->reference}"
            );
        }
    }

    private function resolvePaymentStatus(Order $order): string
    {
        $paid  = (float) $order->amount_paid;
        $total = (float) $order->grand_total;

        if ($paid <= 0) {
            return 'unpaid';
        }
        if ($paid >= $total) {
            return 'paid';
        }

        return 'partial';
    }

    // ── Notification helpers ──────────────────────────────────────────────────

    private function notifyOrderPlaced(Order $order): void
    {
        try {
            \App\Jobs\SendOrderPlacedNotifications::dispatch($order->id);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('OrderPlaced notification dispatch failed', ['error' => $e->getMessage(), 'order' => $order->reference]);
        }
    }

    private function notifyStatusChange(Order $order, string $previousStatus): void
    {
        // Only notify on meaningful status changes
        $notifyStatuses = ['confirmed', 'processing', 'shipping to station', 'out for delivery', 'ready for pick up', 'delivered', 'cancelled', 'pickup window expired'];
        if (! in_array($order->status, $notifyStatuses, true)) {
            return;
        }

        try {
            $order->load('customer', 'items.product', 'items.variant', 'pickupStation');

            if ($order->customer) {
                $order->customer->notify(new OrderStatusNotification($order, $previousStatus));
            } elseif ($order->guest_email) {
                \Illuminate\Support\Facades\Mail::to($order->guest_email)
                    ->send(new OrderStatusNotification($order, $previousStatus));
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('OrderStatus notification failed', ['error' => $e->getMessage(), 'order' => $order->reference]);
        }
    }
}
