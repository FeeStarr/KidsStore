<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Contracts\InventoryServiceInterface;
use Illuminate\Support\Facades\DB;
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
        return DB::transaction(function () use ($data) {
            $order = Order::create([
                'reference'     => $data['reference'] ?? $this->generateReference(),
                'customer_id'   => $data['customer_id'] ?? null,
                'order_date'    => $data['order_date'],
                'status'        => $data['status'] ?? 'order placed',
                'discount'      => (float) ($data['discount'] ?? 0),
                'shipping_fee'  => (float) ($data['shipping_fee'] ?? 0),
                'note'          => $data['note'] ?? null,
            ]);

            foreach ($data['items'] as $row) {
                $this->createItem($order, $row);
            }

            $this->recalculateTotals($order);

            // Decrease inventory for confirmed/processing/shipped/ready/delivered orders.
            if (in_array($order->status, ['confirmed', 'processing', 'shipped', 'ready for pick up', 'delivered'], true)) {
                $this->applyInventoryDecrease($order);
            }

            return $order->fresh('items.product');
        });
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

            if (in_array($order->status, ['confirmed', 'processing', 'shipped', 'ready for pick up', 'delivered'], true)) {
                return $order;
            }

            $this->applyInventoryDecrease($order);
            $order->update(['status' => 'confirmed']);

            return $order->fresh();
        });
    }

    public function markProcessing(Order $order): Order
    {
        if ($order->status === 'order placed') {
            $this->confirm($order);
        }
        $order->update(['status' => 'processing']);

        return $order->fresh();
    }

    public function markShipped(Order $order): Order
    {
        if ($order->status === 'order placed') {
            $this->confirm($order);
        }
        $order->update(['status' => 'shipped']);

        return $order->fresh();
    }

    public function markReadyForPickup(Order $order): Order
    {
        if ($order->status === 'order placed') {
            $this->confirm($order);
        }
        $order->update(['status' => 'ready for pick up']);

        return $order->fresh();
    }

    public function markDelivered(Order $order): Order
    {
        if ($order->status === 'order placed') {
            $this->confirm($order);
        }
        $order->update(['status' => 'delivered']);

        return $order->fresh();
    }

    /**
     * Cancel an order. If inventory was decreased, restore it.
     */
    public function cancel(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            if (in_array($order->status, ['confirmed', 'processing', 'shipped', 'ready for pick up', 'delivered'], true)) {
                $this->inventory->reverseMovementsFor(Order::class, $order->id, 'Order cancelled');
            }

            $order->update(['status' => 'cancelled']);

            return $order->fresh();
        });
    }

    public function recordPayment(Order $order, float $amount): Order
    {
        return DB::transaction(function () use ($order, $amount) {
            $order->amount_paid = (float) $order->amount_paid + $amount;
            $order->payment_status = $this->resolvePaymentStatus($order);
            $order->save();

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

        return $order->items()->create([
            'product_id'         => $product->id,
            'product_variant_id' => $variant->id,
            'quantity'           => $quantity,
            'unit_price'         => $unitPrice,
            'landed_unit_cost'   => $landedUnitCost,
            'discount'           => $discount,
            'line_total'         => $lineTotal,
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
        $grand = $subtotal - $orderDiscount + (float) $order->shipping_fee;

        $order->update([
            'subtotal'    => $subtotal,
            'grand_total' => max(0, $grand),
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
}
