<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$station = \App\Models\PickupStation::find(1);
$station->update(['pickup_shipping_fee' => 50]);

echo "Station '{$station->name}' fee set to ₦50.00\n";
