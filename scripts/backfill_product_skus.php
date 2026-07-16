<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Product;
use Illuminate\Support\Facades\DB;

echo "Starting product SKU backfill...\n";

DB::transaction(function () {
    $prefix = trim((string) (env('STORE_PREFIX', 'KF')));
    $products = Product::whereNull('sku')->orWhere('sku', '')->get();
    foreach ($products as $p) {
        $productCode = 'P' . str_pad((string) $p->id, 6, '0', STR_PAD_LEFT);
        $sku = strtoupper(($prefix !== '' ? $prefix . '-' : '') . $productCode);
        $p->sku = $sku;
        $p->save();
        echo "Updated product id={$p->id} sku={$p->sku}\n";
    }
});

echo "Product SKU backfill complete.\n";
