<?php

// Usage: php scripts/clean_variant_sizes.php [--dry-run]
require __DIR__ . '/../vendor/autoload.php';

$dry = in_array('--dry-run', $argv, true);

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\VariantSize;

$rows = VariantSize::whereRaw("COALESCE(TRIM(size), '') = ''")->get();
if ($rows->isEmpty()) {
    echo "No variant_sizes with empty size found.\n";
    exit(0);
}

echo "Found " . $rows->count() . " variant_sizes with blank size:\n";
foreach ($rows as $r) {
    echo "- id={$r->id} product_variant_id={$r->product_variant_id} sku={$r->sku} quantity={$r->quantity}\n";
}

if ($dry) {
    echo "\nDry-run: no changes applied. Run without --dry-run to apply updates.\n";
    exit(0);
}

// Apply safe update
$updated = 0;
foreach ($rows as $r) {
    $r->size = 'Unknown';
    $r->save();
    $updated++;
}

echo "\nUpdated {$updated} rows: set size = 'Unknown'.\n";
exit(0);
