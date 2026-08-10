<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentTransaction;
use App\Services\OrderService;
use App\Services\PaystackService;
use Illuminate\Support\Facades\DB;

$txnId = (int) ($argv[1] ?? 0);
if (! $txnId) {
    echo "Usage: php scripts/confirm-txn.php <payment_transaction_id> [paystack_reference]\n";
    exit(1);
}

$txn = PaymentTransaction::find($txnId);
if (! $txn) {
    echo "Transaction not found\n";
    exit(1);
}

$order = $txn->order;
echo "Current: txn={$txn->id} status={$txn->status} | order={$order->reference} status={$order->status} payment_status={$order->payment_status}\n";

// If an explicit Paystack reference was supplied, persist it for lookup
$reference = $argv[2] ?? null;
if ($reference) {
    $payload = (array) $txn->opay_payload;
    $payload['data']['reference'] = $reference;
    $txn->update(['opay_payload' => $payload]);
    echo "Stored Paystack reference: {$reference}\n";
}

// If already fully processed, nothing to do
if ($txn->status === 'success' && $order->payment_status === 'paid') {
    echo "Already success/paid — verifying inventory is correct...\n";
} else {
    // Resolve + mark success via the service (also records Paystack identifiers)
    $result = app(PaystackService::class)->queryStatus($txn->fresh());
    echo "queryStatus result: {$result->status}\n";
    $order->refresh();
    $txn->refresh();

    if ($txn->status !== 'success') {
        echo "Transaction could not be confirmed (status={$txn->status}). Nothing changed.\n";
        exit(1);
    }
}

// Reconcile inventory: order is now confirmed/paid, so every item must have
// a stock movement recorded. The old confirm-txn-20.php used `variant->decrement('stock')`
// which was a no-op, so earlier orders may be missing their deductions.
$needDeduct = [];

foreach ($order->items as $item) {
    if (! $item->variant) {
        continue;
    }

    $hasMovement = \App\Models\InventoryMovement::where('reference_type', Order::class)
        ->where('reference_id', $order->id)
        ->where('product_variant_id', $item->variant_id)
        ->where('quantity', '<', 0)
        ->exists();

    if (! $hasMovement) {
        $needDeduct[] = $item;
    }
}

if (empty($needDeduct)) {
    echo "Inventory movements already recorded for all items. Nothing to do.\n";
} else {
    DB::transaction(function () use ($needDeduct, $order) {
        foreach ($needDeduct as $item) {
            app(\App\Services\Contracts\InventoryServiceInterface::class)
                ->decreaseFromOrder(
                    $item->variant,
                    $item->quantity,
                    Order::class,
                    $order->id,
                    "Order #{$order->reference}"
                );
            echo "  Deducted {$item->quantity}x from variant #{$item->variant_id}\n";
        }
    });
    echo "Inventory reconciled.\n";
}

echo "Final: txn={$txn->id} status={$txn->status} | order={$order->refresh()->status} payment_status={$order->payment_status}\n";