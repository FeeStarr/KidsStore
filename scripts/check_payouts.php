<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PickupStation;
use App\Models\Order;
use App\Models\PickupPayout;

foreach (PickupStation::orderBy('id')->get() as $s) {
    $pending = Order::where('pickup_station_id', $s->id)
        ->where('delivery_method', 'pickup')
        ->whereHas('items', function ($q) { $q->where('pickup_station_fee_paid', false); })
        ->count();

    $paid = PickupPayout::where('pickup_station_id', $s->id)->count();

    echo sprintf("%d | %s | pending: %d | paid: %d\n", $s->id, $s->name, $pending, $paid);
}

exit(0);
