<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Order;

if (isset($argv[1]) && (int)$argv[1]) {
    $stationId = (int) $argv[1];
    $order = Order::where('pickup_station_id', $stationId)
        ->where('delivery_method', 'pickup')
        ->whereHas('items', function($q){ $q->where('pickup_station_fee_paid', false); })
        ->orderByDesc('order_date')
        ->first();
} else {
    $order = Order::whereNotNull('pickup_station_id')
        ->where('delivery_method', 'pickup')
        ->whereHas('items', function($q){ $q->where('pickup_station_fee_paid', false); })
        ->orderByDesc('order_date')
        ->first();
    $stationId = $order?->pickup_station_id ?? null;
}

if (! $order) {
    echo "No pending pickup orders found.\n";
    exit(0);
}

echo "Open this URL in your browser (pickup portal must be logged in as station {$stationId}):\n";
echo "http://localhost:8000/pickup-portal/dashboard?order_id={$order->id}\n";
echo "And this payouts page: http://localhost:8000/pickup-portal/payouts?status=paid\n";

exit(0);
