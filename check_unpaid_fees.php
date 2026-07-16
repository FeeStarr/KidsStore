<?php

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "Forbidden.";
    exit;
}

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$unpaid = \App\Models\OrderItem::where('pickup_station_fee_paid', false)
    ->whereHas('order', function($q) {
        $q->where('delivery_method', 'pickup');
    })
    ->with('order')
    ->get();

echo "Total unpaid items: " . $unpaid->count() . "\n";

foreach ($unpaid as $item) {
    echo "Order: {$item->order->reference} - Fee: {$item->pickup_station_fee} - Station: {$item->order->pickup_station_id}\n";
}

echo "\nPickup stations:\n";
$stations = \App\Models\PickupStation::all();
foreach ($stations as $station) {
    echo "ID: {$station->id} - Name: {$station->name}\n";
}
