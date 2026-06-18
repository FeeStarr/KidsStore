<?php

// Usage: php scripts/backfill_color_from_options.php [--dry-run]
// Extracts Color/Colour from options JSON and populates the new color column
require __DIR__ . '/../vendor/autoload.php';

$dry = in_array('--dry-run', $argv, true);

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ProductVariant;

$updated = 0;
foreach (ProductVariant::whereNotNull('options')->cursor() as $v) {
    $opts = (array) ($v->options ?? []);
    $color = null;

    // Look for Color or Colour (case-insensitive)
    foreach ($opts as $k => $val) {
        if (strtolower($k) === 'color' || strtolower($k) === 'colour') {
            $color = (string) $val;
            break;
        }
    }

    if ($color) {
        if ($dry) {
            echo "DRY: variant id={$v->id} sku={$v->sku} would set color=\"{$color}\"\n";
        } else {
            $v->update(['color' => $color]);
        }
        $updated++;
    }
}

echo ($dry ? "Dry-run complete. Found {$updated} variants with color in options.\n" : "Backfill complete. Updated {$updated} variants with color.\n");
exit(0);
