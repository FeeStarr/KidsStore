<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Notifications\NotificationRecipients;
use App\Notifications\OrderPlacedNotification;
use App\Notifications\OrderStatusNotification;
use App\Services\Contracts\InventoryServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

/**
 * Encapsulates order business logic. Stock is decreased through InventoryService.
 */
class OrderService
{
    public function __construct(private InventoryServiceInterface $inventory)
    {
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

            $this->recalculateTotals($order);

            // Decrease inventory for confirmed/processing/shipping to station/out for delivery/ready/delivered orders.
            if (in_array($order->status, ['confirmed', 'processing', 'shipping to station', 'out for delivery', 'ready for pick up', 'delivered'], true)) {
                $this->applyInventoryDecrease($order);
            }

            return $order->fresh('items.product');
        });

        // Send notifications outside the transaction (non-critical)
        $this->notifyOrderPlaced($order);

        return $order;
    }

    /**
     * Confirm an order placed: decrease inventory.
     */
    public function confirm(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            if ($order->status === 'cancelled') {
                throw new RuntimeException('Cannot confirm a cancelled order.');
            }

            if (in_array($order->status, ['confirmed', 'processing', 'shipping to station', 'out for delivery', 'ready for pick up', 'delivered'], true)) {
                return $order;
            }

            $prev = $order->status;
            $this->applyInventoryDecrease($order);
            $order->update(['status' => 'confirmed', 'confirmed_at' => now()]);

            $this->notifyStatusChange($order->fresh(), $prev);

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
        $order->update(['status' => 'ready for pick up']);
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
     * Cancel an order. If inventory was decreased, restore it.
     */
    public function cancel(Order $order): Order
    {
        $result = DB::transaction(function () use ($order) {
            $prev = $order->status;

            if (in_array($order->status, ['confirmed', 'processing', 'shipping to station', 'out for delivery', 'ready for pick up', 'delivered'], true)) {
                $this->inventory->reverseMovementsFor(Order::class, $order->id, 'Order cancelled');
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
        $unitPrice = (float) ($row['unit_price'] ?? $variant->selling_price ?: $product->selling_price);
        // Discount is a PERCENTAGE (0-100) applied to the unit price.
        $discount       = (float) ($row['discount'] ?? 0);
        $quantity       = (int) $row['quantity'];
        $discountAmount = $unitPrice * ($discount / 100);
        $lineTotal      = ($unitPrice - $discountAmount) * $quantity;
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
            'landed_unit_cost'   => $landedUnitCost,
            'discount'           => $discount,
            'line_total'         => $lineTotal,
            'pickup_station_fee' => $pickupStationFee,
        ]);
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
        
        // Shipping fee is per-item, so total shipping = per-item fee × total quantity
        $totalQuantity = (int) $order->items()->sum('quantity');
        $totalShippingBeforeDiscount = (float) $order->shipping_fee * $totalQuantity;
        
        // Apply shipping discount from site settings
        $shippingDiscountPct = (float) \App\Models\Setting::get('shipping_discount', 0);
        $shippingDiscountAmount = $totalShippingBeforeDiscount * ($shippingDiscountPct / 100);
        $totalShipping = $totalShippingBeforeDiscount - $shippingDiscountAmount;
        
        $grand = $subtotal - $orderDiscount + $totalShipping;

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
            $order->load('customer', 'items.product', 'items.variant', 'pickupStation');

            // Notify the customer
            if ($order->customer) {
                $order->customer->notify(new OrderPlacedNotification($order));
            }

            // Notify admin users
            foreach (NotificationRecipients::adminUsers() as $admin) {
                $admin->notify(new OrderPlacedNotification($order));
            }

            // Notify customer support staff
            foreach (NotificationRecipients::internalStaff() as $staff) {
                $staff->notify(new OrderPlacedNotification($order));
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('OrderPlaced notification failed', ['error' => $e->getMessage(), 'order' => $order->reference]);
        }
    }

    private function notifyStatusChange(Order $order, string $previousStatus): void
    {
        // Only notify on meaningful status changes
        $notifyStatuses = ['confirmed', 'processing', 'shipping to station', 'out for delivery', 'ready for pick up', 'delivered', 'cancelled'];
        if (! in_array($order->status, $notifyStatuses, true)) {
            return;
        }

        try {
            $order->load('customer', 'items.product', 'items.variant', 'pickupStation');

            if ($order->customer) {
                $order->customer->notify(new OrderStatusNotification($order, $previousStatus));
            }

            // Notify admin users
            foreach (NotificationRecipients::adminUsers() as $admin) {
                $admin->notify(new OrderStatusNotification($order, $previousStatus));
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('OrderStatus notification failed', ['error' => $e->getMessage(), 'order' => $order->reference]);
        }
    }
}
