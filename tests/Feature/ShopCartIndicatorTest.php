<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use Tests\TestCase;

class ShopCartIndicatorTest extends TestCase
{
    private function makeProductWithVariant(int $stock = 5): array
    {
        $product = Product::create([
            'name'          => 'Cart Badge Product',
            'slug'          => 'cart-badge-product-' . uniqid(),
            'sku'           => 'CBP' . rand(1000, 9999),
            'selling_price' => 5000,
            'status'        => 'active',
            'is_active'     => true,
        ]);

        $variant = ProductVariant::create([
            'product_id'    => $product->id,
            'sku'           => 'CBV' . rand(1000, 9999),
            'selling_price' => 5000,
            'is_active'     => true,
        ]);

        Inventory::create([
            'product_id'         => $product->id,
            'product_variant_id' => $variant->id,
            'quantity'           => $stock,
        ]);

        return [$product, $variant];
    }

    public function test_shop_page_shows_no_in_cart_badge_when_cart_is_empty(): void
    {
        $this->get('/shop')
            ->assertOk()
            ->assertDontSee('In cart (');
    }

    public function test_shop_page_shows_in_cart_badge_when_item_in_cart(): void
    {
        [, $variant] = $this->makeProductWithVariant();

        $this->post('/cart/' . $variant->id)->assertRedirect();

        $this->get('/shop')
            ->assertOk()
            ->assertSee('In cart (1)');
    }

    public function test_badge_shows_accumulated_quantity_across_adds(): void
    {
        [, $variant] = $this->makeProductWithVariant();

        $this->post('/cart/' . $variant->id);
        $this->post('/cart/' . $variant->id);

        $this->get('/shop')
            ->assertOk()
            ->assertSee('In cart (2)');
    }
}
