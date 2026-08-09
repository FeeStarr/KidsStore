<?php

namespace App\Services\Contracts;

use App\Models\InventoryMovement;
use App\Models\ProductVariant;

interface InventoryServiceInterface
{
    /**
     * Increase stock from a purchase.
     */
    public function increaseFromPurchase(ProductVariant $variant, int $quantity, string $referenceType, int $referenceId, ?string $note = null): InventoryMovement;

    /**
     * Decrease stock from an order.
     */
    public function decreaseFromOrder(ProductVariant $variant, int $quantity, string $referenceType, int $referenceId, ?string $note = null): InventoryMovement;

    /**
     * Reverse a previously recorded movement (used when cancelling/deleting).
     */
    public function reverseMovementsFor(string $referenceType, int $referenceId, ?string $note = null): void;

    /**
     * Manually adjust stock by a signed delta (positive or negative).
     */
    public function adjustStock(ProductVariant $variant, int $delta, string $reason, ?string $note = null): InventoryMovement;

    /**
     * Current quantity of a variant.
     */
    public function currentQuantity(ProductVariant $variant): int;

    /**
     * Restore stock from a customer return.
     */
    public function restoreFromReturn(ProductVariant $variant, int $quantity, string $referenceType, int $referenceId, ?string $note = null): InventoryMovement;

    /**
     * Deduct stock for an exchange (replacement variant sent to customer).
     */
    public function deductForExchange(ProductVariant $variant, int $quantity, string $referenceType, int $referenceId, ?string $note = null): InventoryMovement;
}
