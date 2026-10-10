<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

class RefreshStockCache extends Command
{
    protected $signature = 'stock:refresh';

    protected $description = 'Resync products.stock_quantity from variant inventories (safe after bulk SQL changes).';

    public function handle(): int
    {
        $updated = 0;

        Product::query()
            ->select(['id', 'name', 'stock_quantity'])
            ->orderBy('id')
            ->chunkById(100, function ($products) use (&$updated) {
                foreach ($products as $product) {
                    $before = (int) $product->stock_quantity;

                    $product->refreshStock();

                    if ($product->wasChanged('stock_quantity')) {
                        $updated++;
                        $this->line("  #{$product->id} {$product->name}: {$before} -> {$product->stock_quantity}");
                    }
                }
            });

        $this->info("Stock cache refreshed. {$updated} product(s) updated.");

        return self::SUCCESS;
    }
}
