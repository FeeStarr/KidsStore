<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Order;
use App\Models\PickupPayout;
use App\Models\PickupPayoutItem;
use App\Models\PickupStation;

$order = Order::whereNotNull('pickup_station_id')->where('delivery_method','pickup')->orderByDesc('order_date')->first();
if (! $order) {
    echo "No pickup orders found to demo.\n";
    exit(1);
}

$station = PickupStation::find($order->pickup_station_id);
$feePct = $station?->fee_pct ?? 0;
$fee = round($order->grand_total * ((float)$feePct) / 100, 2);

$payout = PickupPayout::create([
    'pickup_station_id' => $order->pickup_station_id,
    'amount' => $fee,
    'created_by' => 1,
    'reference' => 'PP-'.str_pad((string)(PickupPayout::max('id') + 1),6,'0',STR_PAD_LEFT),
    'note' => 'Demo payout',
]);

PickupPayoutItem::create([
    'pickup_payout_id' => $payout->id,
    'order_id' => $order->id,
    'fee_amount' => $fee,
]);

\App\Models\OrderItem::where('order_id', $order->id)->update(['pickup_station_fee_paid' => true, 'pickup_station_fee_paid_at' => now()]);

echo "Created demo payout {$payout->reference} for order {$order->id} (station {$order->pickup_station_id}).\n";
echo "Visit: http://localhost:8000/pickup-portal/payouts?status=paid to view it (login as station).\n";
echo "Or admin ledger: http://localhost:8000/admin/pickup-payouts/records?station_id={$order->pickup_station_id}\n";

exit(0);
