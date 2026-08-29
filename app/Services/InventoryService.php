<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Contracts\InventoryServiceInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Single source of truth for stock changes. Stock is tracked per ProductVariant.
 */
class InventoryService implements InventoryServiceInterface
{
    public function increaseFromPurchase(
        ProductVariant $variant,
        int $quantity,
        string $referenceType,
        int $referenceId,
        ?string $note = null
    ): InventoryMovement {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Purchase quantity must be positive.');
        }

        return DB::transaction(function () use ($variant, $quantity, $referenceType, $referenceId, $note) {
            $inventory = $this->lockInventory($variant);
            $inventory->quantity += $quantity;
            $inventory->save();

            $this->refreshProductStock($variant->product_id);

            return $this->recordMovement($variant, 'purchase', $quantity, $referenceType, $referenceId, $note);
        });
    }

    public function decreaseFromOrder(
        ProductVariant $variant,
        int $quantity,
        string $referenceType,
        int $referenceId,
        ?string $note = null
    ): InventoryMovement {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Order quantity must be positive.');
        }

        return DB::transaction(function () use ($variant, $quantity, $referenceType, $referenceId, $note) {
            $inventory = $this->lockInventory($variant);

            if ($inventory->quantity < $quantity) {
                $label = $variant->display_label;
                throw new RuntimeException(
                    "Insufficient stock for '{$label}'. Available: {$inventory->quantity}, requested: {$quantity}."
                );
            }

            $inventory->quantity -= $quantity;
            $inventory->save();

            $this->refreshProductStock($variant->product_id);

            return $this->recordMovement($variant, 'sale', -$quantity, $referenceType, $referenceId, $note);
        });
    }

    public function reverseMovementsFor(string $referenceType, int $referenceId, ?string $note = null): void
    {
        DB::transaction(function () use ($referenceType, $referenceId, $note) {
            $movements = InventoryMovement::where('reference_type', $referenceType)
                ->where('reference_id', $referenceId)
                ->get();

            $productIds = collect();

            foreach ($movements as $movement) {
                if (! $movement->product_variant_id) {
                    continue;
                }
                $variant = ProductVariant::findOrFail($movement->product_variant_id);
                $inventory = $this->lockInventory($variant);

                $inverse = -1 * $movement->quantity;

                if ($inventory->quantity + $inverse < 0) {
                    throw new RuntimeException(
                        "Cannot reverse movement for '{$variant->display_label}': would result in negative stock."
                    );
                }

                $inventory->quantity += $inverse;
                $inventory->save();

                $productIds->push($variant->product_id);

                $this->recordMovement(
                    $variant,
                    'adjustment',
                    $inverse,
                    $referenceType,
                    $referenceId,
                    $note ?? 'Reversal of '.class_basename($referenceType).' #'.$referenceId
                );
            }

            foreach ($productIds->unique() as $productId) {
                $this->refreshProductStock($productId);
            }
        });
    }

    public function currentQuantity(ProductVariant $variant): int
    {
        return (int) ($variant->inventory()->value('quantity') ?? 0);
    }

    public function restoreFromReturn(
        ProductVariant $variant,
        int $quantity,
        string $referenceType,
        int $referenceId,
        ?string $note = null
    ): InventoryMovement {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Return quantity must be positive.');
        }

        return DB::transaction(function () use ($variant, $quantity, $referenceType, $referenceId, $note) {
            $inventory = $this->lockInventory($variant);
            $inventory->quantity += $quantity;
            $inventory->save();

            $this->refreshProductStock($variant->product_id);

            return $this->recordMovement($variant, 'return', $quantity, $referenceType, $referenceId, $note);
        });
    }

    public function deductForExchange(
        ProductVariant $variant,
        int $quantity,
        string $referenceType,
        int $referenceId,
        ?string $note = null
    ): InventoryMovement {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Exchange quantity must be positive.');
        }

        return DB::transaction(function () use ($variant, $quantity, $referenceType, $referenceId, $note) {
            $inventory = $this->lockInventory($variant);

            if ($inventory->quantity < $quantity) {
                $label = $variant->display_label;
                throw new RuntimeException(
                    "Insufficient stock for exchange variant '{$label}'. Available: {$inventory->quantity}, needed: {$quantity}."
                );
            }

            $inventory->quantity -= $quantity;
            $inventory->save();

            $this->refreshProductStock($variant->product_id);

            return $this->recordMovement($variant, 'exchange', -$quantity, $referenceType, $referenceId, $note);
        });
    }

    public function adjustStock(ProductVariant $variant, int $delta, string $reason, ?string $note = null): InventoryMovement
    {
        if ($delta >= 0) {
            throw new InvalidArgumentException('Stock increases are only allowed through purchases.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('A reason is required for stock adjustments.');
        }

        return DB::transaction(function () use ($variant, $delta, $reason, $note) {
            $inventory = $this->lockInventory($variant);

            if ($inventory->quantity + $delta < 0) {
                throw new RuntimeException(
                    "Adjustment would result in negative stock for '{$variant->display_label}'. ".
                    "Current: {$inventory->quantity}, requested change: {$delta}."
                );
            }

            $inventory->quantity += $delta;
            $inventory->save();

            $this->refreshProductStock($variant->product_id);

            $combinedNote = $note ? $reason.' - '.$note : $reason;

            return InventoryMovement::create([
                'product_id'         => $variant->product_id,
                'product_variant_id' => $variant->id,
                'type'               => 'adjustment',
                'quantity'           => $delta,
                'reference_type'     => null,
                'reference_id'       => null,
                'note'               => $combinedNote,
            ]);
        });
    }

    private function lockInventory(ProductVariant $variant): Inventory
    {
        $inventory = Inventory::where('product_variant_id', $variant->id)->lockForUpdate()->first();

        if (! $inventory) {
            Inventory::create([
                'product_id'         => $variant->product_id,
                'product_variant_id' => $variant->id,
                'quantity'           => 0,
                'reorder_level'      => 5,
            ]);
            $inventory = Inventory::where('product_variant_id', $variant->id)->lockForUpdate()->first();
        }

        return $inventory;
    }

    private function refreshProductStock(int $productId): void
    {
        Product::where('id', $productId)->first()?->refreshStock();
    }

    private function recordMovement(
        ProductVariant $variant,
        string $type,
        int $signedQuantity,
        string $referenceType,
        int $referenceId,
        ?string $note
    ): InventoryMovement {
        return InventoryMovement::create([
            'product_id'         => $variant->product_id,
            'product_variant_id' => $variant->id,
            'type'               => $type,
            'quantity'           => $signedQuantity,
            'reference_type'     => $referenceType,
            'reference_id'       => $referenceId,
            'note'               => $note,
        ]);
    }
}
