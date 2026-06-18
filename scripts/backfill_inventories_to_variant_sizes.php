<?php

// Usage: php scripts/backfill_inventories_to_variant_sizes.php [--dry-run]
// This script associates existing variant_sizes with inventories by filling `variant_size_id`.
require __DIR__ . '/../vendor/autoload.php';

$dry = in_array('--dry-run', $argv, true);

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\VariantSize;
use App\Models\Inventory;
use Illuminate\Support\Facades\Schema;

$progress = 0;
foreach (VariantSize::with('productVariant')->cursor() as $vs) {
    // Try to find an inventory row by product_variant_id and (sku if available)
    $invQuery = Inventory::where('product_variant_id', $vs->product_variant_id);
    if (Schema::hasColumn('inventories', 'sku')) {
        $invQuery = $invQuery->where(function ($q) use ($vs) {
            $q->where('sku', $vs->sku)->orWhereNull('sku');
        });
    }
    $inv = $invQuery->first();

    if (! $inv) {
        // Create new inventory row mapping to this variant_size
        $inv = new Inventory();
        $inv->product_variant_id = $vs->product_variant_id;
        if (Schema::hasColumn('inventories', 'sku')) {
            $inv->sku = $vs->sku;
        }
        $inv->quantity = $vs->quantity ?? 0;
    }

    $inv->variant_size_id = $vs->id;

    if ($dry) {
        echo "DRY: would set inventory(product_variant_id={$inv->product_variant_id}, sku={$inv->sku}) -> variant_size_id={$vs->id} quantity={$inv->quantity}\n";
    } else {
        $inv->save();
    }

    $progress++;
}

echo ($dry ? "Dry-run complete. Processed {$progress} variant_sizes.\n" : "Backfill complete. Processed {$progress} variant_sizes.\n");

exit(0);
