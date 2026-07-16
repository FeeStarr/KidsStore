<?php

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "Forbidden.";
    exit;
}

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$station = \App\Models\PickupStation::find(1);

echo "Station: {$station->name}\n";
echo "Pickup shipping fee: {$station->pickup_shipping_fee}\n\n";

// Update existing order items with fees
$items = \App\Models\OrderItem::where('pickup_station_fee_paid', false)
    ->whereHas('order', function($q) {
        $q->where('delivery_method', 'pickup')->where('pickup_station_id', 1);
    })
    ->get();

$fee = $station->pickup_shipping_fee ?? 50.00;

echo "Updating {$items->count()} items with fee of {$fee}...\n";

foreach ($items as $item) {
    $itemFee = $fee * $item->quantity;
    $item->update(['pickup_station_fee' => $itemFee]);
    echo "Updated item #{$item->id} - Qty: {$item->quantity} - Fee: {$itemFee}\n";
}

echo "\nDone!\n";
