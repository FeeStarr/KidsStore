<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ProductVariant;
use App\Services\ProductService;
use Illuminate\Support\Facades\DB;

echo "Starting variant SKU backfill...\n";

DB::transaction(function () {
    $svc = new ProductService();
    $variants = ProductVariant::whereNull('sku')->orWhere('sku', '')->get();
    foreach ($variants as $v) {
        $product = $v->product;
        if (! $product) {
            echo "Skipping variant id={$v->id} (no product)\n";
            continue;
        }

        $candidate = $svc->generateVariantSku($product, ['color_id' => $v->color_id, 'age_range_id' => $v->age_range_id]);
        $sku = $svc->ensureUniqueSku($candidate);

        $v->sku = $sku;
        $v->save();
        echo "Updated variant id={$v->id} sku={$v->sku}\n";
    }
});

echo "Backfill complete.\n";
