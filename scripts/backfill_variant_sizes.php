<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductImage;
use App\Models\VariantSize;
use Illuminate\Support\Facades\DB;

echo "Starting variant sizes backfill...\n";

DB::transaction(function () {
    $products = Product::with(['variants.inventory', 'variants.images'])->get();
    foreach ($products as $product) {
        $variants = $product->variants;
        if ($variants->count() <= 1) {
            continue;
        }

        $sized = $variants->filter(function ($v) {
            return is_array($v->options) && array_key_exists('size', $v->options) && $v->options['size'] !== null && $v->options['size'] !== '';
        });

        if ($sized->isEmpty()) {
            continue;
        }

        $others = $variants->diff($sized);

        // Choose parent: prefer an existing non-sized variant, else create a new parent.
        if ($others->isNotEmpty()) {
            $parent = $others->first();
            echo "Using existing parent variant id={$parent->id} for product id={$product->id}\n";
        } else {
            $first = $variants->first();
            $parent = ProductVariant::create([
                'product_id' => $product->id,
                'sku' => $first->sku ? $first->sku . '-P' : strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)),
                'name' => $first->name ?? 'Variant',
                'options' => null,
                'selling_price' => $first->selling_price ?? 0,
                'discount' => $first->discount ?? 0,
                'is_active' => $first->is_active ?? true,
            ]);
            echo "Created parent variant id={$parent->id} for product id={$product->id}\n";
        }

        foreach ($sized as $v) {
            // Skip if this is the chosen parent
            if ($v->id === $parent->id) {
                continue;
            }

            $sizeKey = $v->options['size'] ?? null;

            // Find or create VariantSize under parent
            $existing = $parent->sizes()->where('size', $sizeKey)->orWhere('sku', $v->sku)->first();
            if (! $existing) {
                $created = VariantSize::create([
                    'product_variant_id' => $parent->id,
                    'size' => $sizeKey,
                    'sku' => $v->sku,
                    'quantity' => $v->inventory?->quantity ?? 0,
                    'reorder_level' => $v->inventory?->reorder_level ?? 5,
                ]);
                echo "Created VariantSize id={$created->id} size={$sizeKey} sku={$created->sku}\n";
            } else {
                // update existing size row quantity if inventory exists
                $existing->quantity = $v->inventory?->quantity ?? $existing->quantity;
                $existing->reorder_level = $v->inventory?->reorder_level ?? $existing->reorder_level ?? 5;
                $existing->save();
                echo "Updated existing VariantSize id={$existing->id} size={$sizeKey}\n";
            }

            // Reassign images to parent variant
            foreach ($v->images as $img) {
                $img->product_variant_id = $parent->id;
                $img->save();
            }

            // Delete inventory row for old variant (if any)
            if ($v->inventory) {
                $v->inventory->delete();
            }

            // Finally delete the old sized variant
            $vid = $v->id;
            $v->delete();
            echo "Deleted old sized ProductVariant id={$vid}\n";
        }
    }
});

echo "Backfill complete.\n";
