<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PaymentTransaction;
use App\Models\Order;

$txn = PaymentTransaction::find(20);
if (!$txn) { echo "Transaction not found\n"; exit(1); }

echo "Current: txn_status={$txn->status} order_status={$txn->order->status} payment_status={$txn->order->payment_status}\n";

// Store Paystack reference
$payload = (array) $txn->opay_payload;
$payload['data']['reference'] = '00001320260810141711000000010738750';
$txn->update(['opay_payload' => $payload]);
echo "Stored Paystack reference\n";

// Verify via curl directly
$key = config('paystack.secret_key');
$ref = '00001320260810141711000000010738750';
$ch = curl_init("https://api.paystack.co/transaction/verify/{$ref}");
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $key]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$resp = curl_exec($ch);
$data = json_decode($resp, true);
curl_close($ch);

if (!($data['status'] ?? false)) {
    echo "API verification failed: " . ($data['message'] ?? 'unknown') . "\n";
    exit(1);
}

$paystackStatus = strtolower($data['data']['status'] ?? '');
$paystackAmount = (float) ($data['data']['amount'] ?? 0) / 100;
echo "Paystack: status={$paystackStatus} amount={$paystackAmount}\n";

if ($paystackStatus !== 'success') {
    echo "Payment not successful on Paystack\n";
    exit(1);
}

if (abs($paystackAmount - $txn->amount) > 1) {
    echo "Amount mismatch: expected {$txn->amount} got {$paystackAmount}\n";
    exit(1);
}

// Mark transaction as success
$txn->update([
    'status' => 'success',
    'opay_payload' => $data,
]);
echo "Transaction marked as success\n";

// Confirm the order
$order = $txn->order;
$order->update([
    'status' => 'confirmed',
    'payment_status' => 'paid',
    'confirmed_at' => $order->confirmed_at ?? now(),
]);

// Decrease stock for each item
foreach ($order->items as $item) {
    if ($item->productVariant) {
        $item->productVariant->decrement('stock', $item->quantity);
    }
}

echo "Done! Order {$order->reference} confirmed\n";
echo "Final: txn_status={$txn->status} order_status={$order->status} payment_status={$order->payment_status}\n";
