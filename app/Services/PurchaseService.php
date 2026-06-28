<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Services\Contracts\InventoryServiceInterface;
use Illuminate\Support\Facades\DB;

/**
 * Encapsulates purchase business logic. Inventory updates are delegated
 * to InventoryService so stock is changed only through purchases (and orders).
 */
class PurchaseService
{
    public function __construct(private InventoryServiceInterface $inventory)
    {
    }

    /**
     * Create a purchase with its items, calculate totals, and update inventory
     * for each item when the purchase is marked as 'received'.
     *
     * @param array{
     *   reference?: string,
     *   supplier_id?: int|null,
     *   purchase_date: string,
     *   status?: string,
     *   note?: string|null,
     *   items: array<int, array{
     *     product_id:int,quantity:int,
     *     cost_price:float,shipping_fee?:float,packaging_cost?:float,
     *     other_costs?:float,selling_price?:float,discount?:float
     *   }>
     * } $data
     */
    public function create(array $data): Purchase
    {
        return DB::transaction(function () use ($data) {
            $purchaseNumber = $data['purchase_number'] ?? $data['reference'] ?? $this->generateReference();
            $purchase = Purchase::create([
                'purchase_number' => $purchaseNumber,
                'reference'       => $purchaseNumber,
                'supplier_id'     => $data['supplier_id'] ?? null,
                'purchase_date'   => $data['purchase_date'],
                'status'          => $data['status'] ?? 'pending',
                'note'            => $data['note'] ?? null,
                'pickup_fee_pct'  => (float) ($data['pickup_fee_pct'] ?? 0),
            ]);

            foreach ($data['items'] as $row) {
                $this->createItem($purchase, $row);
            }

            $this->recalculateTotals($purchase);

            if ($purchase->status === 'received') {
                $this->applyInventoryIncrease($purchase);
            }

            return $purchase->fresh('items.product');
        });
    }

    /**
     * Mark a pending purchase as received and increase inventory.
     */
    public function markReceived(Purchase $purchase): Purchase
    {
        return DB::transaction(function () use ($purchase) {
            if ($purchase->status === 'received') {
                return $purchase;
            }

            if ($purchase->status === 'cancelled') {
                throw new \RuntimeException('Cannot receive a cancelled purchase.');
            }

            $purchase->update(['status' => 'received']);
            $this->applyInventoryIncrease($purchase);

            return $purchase->fresh('items');
        });
    }

    /**
     * Cancel a purchase. If it was already received, reverse the inventory increase.
     */
    public function cancel(Purchase $purchase): Purchase
    {
        return DB::transaction(function () use ($purchase) {
            if ($purchase->status === 'received') {
                $this->inventory->reverseMovementsFor(Purchase::class, $purchase->id, 'Purchase cancelled');
            }

            $purchase->update(['status' => 'cancelled']);

            return $purchase->fresh();
        });
    }

    /**
     * Replace all items on a pending purchase and recalculate totals.
     * Only allowed while status = 'pending' (inventory not yet touched).
     */
    public function update(Purchase $purchase, array $data): Purchase
    {
        if ($purchase->status !== 'pending') {
            throw new \RuntimeException('Only pending purchases can be edited.');
        }

        return DB::transaction(function () use ($purchase, $data) {
            $purchase->update([
                'purchase_number' => $data['purchase_number'] ?? $purchase->purchase_number,
                'reference'       => $data['purchase_number'] ?? $purchase->reference,
                'supplier_id'     => $data['supplier_id'] ?? null,
                'purchase_date'   => $data['purchase_date'],
                'note'            => $data['note'] ?? null,
                'pickup_fee_pct'  => (float) ($data['pickup_fee_pct'] ?? 0),
            ]);

            // Replace all items
            $purchase->items()->delete();

            foreach ($data['items'] as $row) {
                $this->createItem($purchase, $row);
            }

            $this->recalculateTotals($purchase);

            return $purchase->fresh('items.product');
        });
    }

    public function delete(Purchase $purchase): void
    {
        if ($purchase->status !== 'pending') {
            throw new \RuntimeException('Only pending purchases can be deleted.');
        }

        DB::transaction(function () use ($purchase) {
            // Delete all items first
            $purchase->items()->delete();
            // Delete the purchase
            $purchase->delete();
        });
    }

    public function generateReference(): string
    {
        $next = (Purchase::max('id') ?? 0) + 1;

        return 'PO-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    private function createItem(Purchase $purchase, array $row): PurchaseItem
    {
        $variant = $this->resolveVariant($row);

        $costPrice      = (float) ($row['cost_price'] ?? 0);
        $shippingFee    = (float) ($row['shipping_fee'] ?? 0);
        $packagingCost  = (float) ($row['packaging_cost'] ?? 0);
        $otherCosts     = (float) ($row['other_costs'] ?? 0);
        $sellingPrice   = (float) ($row['selling_price'] ?? 0);
        $discount       = (float) ($row['discount'] ?? 0);
        $quantity       = (int) $row['quantity'];

        // Discount is a PERCENTAGE (0-100) applied to the per-unit landed cost.
        $unitCost       = $costPrice + $shippingFee + $packagingCost + $otherCosts;
        $discountAmount = $unitCost * ($discount / 100);
        $lineTotal      = ($unitCost - $discountAmount) * $quantity;

        return $purchase->items()->create([
            'product_id'         => $variant->product_id,
            'product_variant_id' => $variant->id,
            'quantity'           => $quantity,
            'cost_price'         => $costPrice,
            'shipping_fee'       => $shippingFee,
            'packaging_cost'     => $packagingCost,
            'other_costs'        => $otherCosts,
            'selling_price'      => $sellingPrice,
            'discount'           => $discount,
            'line_total'         => $lineTotal,
        ]);
    }

    /**
     * Resolve the target variant for a line. Accepts either an explicit
     * product_variant_id, or falls back to the product's default variant.
     */
    private function resolveVariant(array $row): ProductVariant
    {
        if (! empty($row['product_variant_id'])) {
            return ProductVariant::findOrFail($row['product_variant_id']);
        }
        $product = Product::with('defaultVariant')->findOrFail($row['product_id']);
        $variant = $product->defaultVariant;
        if (! $variant) {
            throw new \RuntimeException("Product '{$product->name}' has no variants.");
        }
        return $variant;
    }

    private function recalculateTotals(Purchase $purchase): void
    {
        $items = $purchase->items()->get();

        $totals = [
            'total_cost_price'     => 0,
            'total_shipping_fee'   => 0,
            'total_packaging_cost' => 0,
            'total_other_costs'    => 0,
            'total_discount'       => 0,
            'total_cost'           => 0,
            'grand_total'          => 0,
        ];

        foreach ($items as $item) {
            $unitCost       = (float) $item->cost_price + (float) $item->shipping_fee
                            + (float) $item->packaging_cost + (float) $item->other_costs;
            $discountAmount = $unitCost * ((float) $item->discount / 100) * $item->quantity;

            $totals['total_cost_price']     += (float) $item->cost_price * $item->quantity;
            $totals['total_shipping_fee']   += (float) $item->shipping_fee * $item->quantity;
            $totals['total_packaging_cost'] += (float) $item->packaging_cost * $item->quantity;
            $totals['total_other_costs']    += (float) $item->other_costs * $item->quantity;
            $totals['total_discount']       += $discountAmount; // monetary value of percentage discount
            $totals['grand_total']          += (float) $item->line_total;
        }

        $totals['total_cost'] = $totals['grand_total'];

        $purchase->update($totals);
    }

    private function applyInventoryIncrease(Purchase $purchase): void
    {
        foreach ($purchase->items()->with('variant.product')->get() as $item) {
            $variant = $item->variant;
            if (! $variant) {
                continue;
            }
            $this->inventory->increaseFromPurchase(
                $variant,
                $item->quantity,
                Purchase::class,
                $purchase->id,
                "Purchase #{$purchase->display_number}"
            );

            // Update the variant's selling price from this purchase line.
            if ((float) $item->selling_price > 0) {
                $variant->update(['selling_price' => $item->selling_price]);
            }
        }
    }
}
