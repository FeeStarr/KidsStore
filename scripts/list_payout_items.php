<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PickupPayoutItem;

$items = PickupPayoutItem::with('payout','order')->orderByDesc('id')->take(10)->get();
foreach ($items as $it) {
    printf("id=%d payout=%d order=%s fee=%s\n", $it->id, $it->pickup_payout_id, $it->order_id ?? 'NULL', (string)$it->fee_amount);
}

exit(0);
