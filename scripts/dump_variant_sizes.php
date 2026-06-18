<?php

// Usage: php scripts/dump_variant_sizes.php <product_id>
require __DIR__ . '/../vendor/autoload.php';

$productId = $argv[1] ?? null;
if (! $productId) {
    echo "Usage: php scripts/dump_variant_sizes.php <product_id>\n";
    exit(2);
}

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Product;

try {
    $p = Product::with(['variants.sizes'])->find($productId);
    if (! $p) {
        echo "Product not found: {$productId}\n";
        exit(1);
    }

    $out = [];
    foreach ($p->variants as $v) {
        $sizes = [];
        foreach ($v->sizes as $s) {
            $sizes[] = [
                'id' => $s->id,
                'size' => (string) $s->size,
                'sku' => $s->sku,
                'quantity' => $s->quantity,
            ];
        }
        $out[] = [
            'variant_id' => $v->id,
            'variant_sku' => $v->sku,
            'sizes' => $sizes,
        ];
    }

    echo json_encode($out, JSON_PRETTY_PRINT) . "\n";
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
