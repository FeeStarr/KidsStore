<?php

// Usage: php scripts/delete_blank_variant_sizes.php [--yes] [--force]
// --yes  : proceed without interactive confirmation
// --force: allow deleting rows that have quantity > 0

require __DIR__ . '/../vendor/autoload.php';

$allowYes = in_array('--yes', $argv, true);
$allowForce = in_array('--force', $argv, true);

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\VariantSize;

$rows = VariantSize::whereRaw("COALESCE(TRIM(size), '') = ''")->get();
if ($rows->isEmpty()) {
    echo "No variant_sizes with empty size found.\n";
    exit(0);
}

$withQty = $rows->filter(fn($r)=>($r->quantity ?? 0) > 0);

echo "Found " . $rows->count() . " variant_sizes with blank size.\n";
if ($withQty->isNotEmpty()) {
    echo "Warning: " . $withQty->count() . " rows have quantity > 0:\n";
    foreach ($withQty as $r) {
        echo "- id={$r->id} product_variant_id={$r->product_variant_id} sku={$r->sku} quantity={$r->quantity}\n";
    }
    if (! $allowForce) {
        echo "\nAborting: use --force to allow deletion of rows with quantity>0.\n";
        exit(2);
    }
}

echo "Listing rows to delete:\n";
foreach ($rows as $r) {
    echo "- id={$r->id} product_variant_id={$r->product_variant_id} sku={$r->sku} quantity={$r->quantity}\n";
}

if (! $allowYes) {
    echo "\nRun with --yes to proceed, or re-run with --yes --force to allow deleting quantity>0 rows.\n";
    exit(0);
}

$ids = $rows->pluck('id')->all();
$deleted = VariantSize::whereIn('id', $ids)->delete();
echo "\nDeleted {$deleted} rows.\n";
exit(0);
