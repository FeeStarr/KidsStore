<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class RefreshStockCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(int $quantity): Product
    {
        $product = Product::create([
            'sku'   => 'SKU-' . uniqid(),
            'name'  => 'Stock Test Product',
            'slug'  => 'stock-test-product-' . uniqid(),
            'selling_price' => 5000,
            'is_active'     => true,
            'status'        => 'active',
        ]);

        $variant = ProductVariant::create([
            'product_id'    => $product->id,
            'sku'           => 'SKU-V-' . uniqid(),
            'name'          => 'Default',
            'selling_price' => 5000,
            'discount'      => 0,
            'is_active'     => true,
        ]);

        Inventory::create([
            'product_id'         => $product->id,
            'product_variant_id' => $variant->id,
            'quantity'           => $quantity,
            'reorder_level'      => 5,
        ]);

        return $product;
    }

    public function test_stock_refresh_resyncs_stale_product_cache(): void
    {
        $product = $this->makeProduct(100);

        Product::where('id', $product->id)->update(['stock_quantity' => 7]);

        Artisan::call('stock:refresh');

        $this->assertSame(100, (int) $product->fresh()->stock_quantity);
        $this->assertStringContainsString('1 product(s) updated', Artisan::output());
    }

    public function test_stock_refresh_is_idempotent_on_correct_caches(): void
    {
        $product = $this->makeProduct(50);

        Artisan::call('stock:refresh');
        $this->assertSame(50, (int) $product->fresh()->stock_quantity);

        Artisan::call('stock:refresh');
        $this->assertStringContainsString('0 product(s) updated', Artisan::output());
    }
}
