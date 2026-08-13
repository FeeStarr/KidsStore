<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CartService;
use App\Services\DealService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DealsFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(float $price): array
    {
        $product = Product::create([
            'sku'   => 'SKU-' . uniqid(),
            'name'  => 'Test Product',
            'slug'  => 'test-product-' . uniqid(),
            'selling_price' => $price,
            'is_active'     => true,
            'status'        => 'active',
        ]);

        $variant = ProductVariant::create([
            'product_id'    => $product->id,
            'sku'           => 'SKU-V-' . uniqid(),
            'name'          => 'Default',
            'selling_price' => $price,
            'discount'      => 0,
            'is_active'     => true,
        ]);

        Inventory::create([
            'product_id'          => $product->id,
            'product_variant_id'  => $variant->id,
            'quantity'            => 100,
            'reorder_level'       => 5,
        ]);

        return [$product, $variant];
    }

    public function test_percentage_deal_price_is_applied(): void
    {
        [$product, $variant] = $this->makeProduct(1000);

        $deal = app(DealService::class)->create([
            'title'         => '20% Off',
            'slug'          => 'pct-20',
            'discount_type' => Deal::TYPE_PERCENTAGE,
            'discount_value' => 20,
            'starts_at'     => now()->subHour(),
            'ends_at'       => now()->addDay(),
            'status'        => Deal::STATUS_ACTIVE,
        ], [$product->id]);

        $this->assertEquals(800.00, $deal->priceFor(1000));
        $this->assertEquals(200.00, $deal->discountFor(1000));

        // Deal replaces the static discount while live.
        $pricing = app(DealService::class)->priceForVariant($variant);
        $this->assertEquals(800.00, $pricing['net_unit']);
        $this->assertEquals($deal->id, $pricing['deal_id']);
        $this->assertEquals(0.0, $pricing['discount']);
    }

    public function test_fixed_amount_deal_never_goes_below_zero(): void
    {
        [$product] = $this->makeProduct(50);

        $deal = app(DealService::class)->create([
            'title'         => 'Flat 40',
            'slug'          => 'flat-40',
            'discount_type' => Deal::TYPE_FIXED_AMOUNT,
            'discount_value' => 40,
            'starts_at'     => now()->subHour(),
            'status'        => Deal::STATUS_ACTIVE,
        ], [$product->id]);

        $this->assertEquals(10.00, $deal->priceFor(50));
        $this->assertEquals(0.00, $deal->priceFor(10));
    }

    public function test_fixed_price_deal_sets_absolute_price(): void
    {
        [$product] = $this->makeProduct(500);

        $deal = app(DealService::class)->create([
            'title'         => 'Locked at 99',
            'slug'          => 'fixed-99',
            'discount_type' => Deal::TYPE_FIXED_PRICE,
            'discount_value' => 99,
            'starts_at'     => now()->subHour(),
            'status'        => Deal::STATUS_ACTIVE,
        ], [$product->id]);

        $this->assertEquals(99.00, $deal->priceFor(500));
    }

    public function test_overlapping_active_deals_are_rejected(): void
    {
        [$product] = $this->makeProduct(100);

        app(DealService::class)->create([
            'title'         => 'Deal A',
            'slug'          => 'overlap-a',
            'discount_type' => Deal::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'starts_at'     => now()->subDay(),
            'ends_at'       => now()->addDays(2),
            'status'        => Deal::STATUS_ACTIVE,
        ], [$product->id]);

        $this->expectException(ValidationException::class);

        app(DealService::class)->create([
            'title'         => 'Deal B',
            'slug'          => 'overlap-b',
            'discount_type' => Deal::TYPE_FIXED_AMOUNT,
            'discount_value' => 500,
            'starts_at'     => now(),
            'ends_at'       => now()->addDay(),
            'status'        => Deal::STATUS_ACTIVE,
        ], [$product->id]);
    }

    public function test_non_overlapping_deals_are_allowed(): void
    {
        [$product] = $this->makeProduct(100);

        app(DealService::class)->create([
            'title'         => 'Deal A',
            'slug'          => 'no-overlap-a',
            'discount_type' => Deal::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'starts_at'     => now()->subDay(),
            'ends_at'       => now()->addHour(),
            'status'        => Deal::STATUS_ACTIVE,
        ], [$product->id]);

        $second = app(DealService::class)->create([
            'title'         => 'Deal B',
            'slug'          => 'no-overlap-b',
            'discount_type' => Deal::TYPE_PERCENTAGE,
            'discount_value' => 15,
            'starts_at'     => now()->addHours(2),
            'ends_at'       => now()->addDays(2),
            'status'        => Deal::STATUS_SCHEDULED,
        ], [$product->id]);

        $this->assertNotNull($second->id);
    }

    public function test_status_lifecycle_sync(): void
    {
        [$product] = $this->makeProduct(100);

        $deal = app(DealService::class)->create([
            'title'         => 'Lifecycle',
            'slug'          => 'lifecycle',
            'discount_type' => Deal::TYPE_PERCENTAGE,
            'discount_value' => 5,
            'starts_at'     => now()->addHour(),
            'ends_at'       => now()->addDays(2),
            'status'        => Deal::STATUS_SCHEDULED,
        ], [$product->id]);

        app(DealService::class)->syncStatuses();
        $this->assertEquals('scheduled', $deal->fresh()->status);

        $deal->update(['starts_at' => now()->subHour()]);
        app(DealService::class)->syncStatuses();
        $this->assertEquals('active', $deal->fresh()->status);

        $deal->update(['ends_at' => now()->subMinute()]);
        app(DealService::class)->syncStatuses();
        $this->assertEquals('expired', $deal->fresh()->status);
    }

    public function test_deal_removes_static_discount_pricing_but_keeps_fallback(): void
    {
        [$product, $variant] = $this->makeProduct(1000);

        // Static discount fallback (no deal).
        $variant->update(['discount' => 25]);
        $pricing = app(DealService::class)->priceForVariant($variant);
        $this->assertEquals(750.00, $pricing['net_unit']);
        $this->assertNull($pricing['deal_id']);

        // Live deal overrides the static discount.
        app(DealService::class)->create([
            'title'         => 'replaces static',
            'slug'          => 'replaces-static',
            'discount_type' => Deal::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'starts_at'     => now()->subHour(),
            'status'        => Deal::STATUS_ACTIVE,
        ], [$product->id]);

        $pricing = app(DealService::class)->priceForVariant($variant);
        $this->assertEquals(900.00, $pricing['net_unit']);
    }

    public function test_cart_line_applies_deal_pricing(): void
    {
        [$product, $variant] = $this->makeProduct(250);

        app(DealService::class)->create([
            'title'         => 'Cart Deal',
            'slug'          => 'cart-deal',
            'discount_type' => Deal::TYPE_PERCENTAGE,
            'discount_value' => 20,
            'starts_at'     => now()->subHour(),
            'ends_at'       => now()->addDay(),
            'status'        => Deal::STATUS_ACTIVE,
        ], [$product->id]);

        $this->app->make(CartService::class)->add($variant->id, 2);
        $line = $this->app->make(CartService::class)->items()->first();

        $this->assertEquals(250.00, $line->unit_price);
        $this->assertEquals(200.00, $line->net_unit);
        $this->assertEquals(400.00, $line->line_total);
        $this->assertEquals(250.00, $line->original_unit_price);
        $this->assertEquals(50.00, $line->discount_amount);
        $this->assertNotNull($line->deal_id);
    }

    public function test_order_snapshot_records_deal_fields(): void
    {
        [$product, $variant] = $this->makeProduct(250);

        $deal = app(DealService::class)->create([
            'title'         => 'Order Deal',
            'slug'          => 'order-deal',
            'discount_type' => Deal::TYPE_PERCENTAGE,
            'discount_value' => 20,
            'starts_at'     => now()->subHour(),
            'status'        => Deal::STATUS_ACTIVE,
        ], [$product->id]);

        $order = app(OrderService::class)->create([
            'customer_id'   => User::factory()->create()->id,
            'order_date'    => now()->toDateString(),
            'status'        => 'confirmed',
            'delivery_method' => 'delivery',
            'items'         => [[
                'product_id'         => $product->id,
                'product_variant_id' => $variant->id,
                'quantity'           => 1,
                'unit_price'         => $variant->selling_price,
                'original_unit_price'=> $variant->selling_price,
                'discount'           => 0,
                'discount_amount'    => 50,
                'deal_id'            => $deal->id,
            ]],
        ]);

        $item = $order->items()->first();
        $this->assertEquals(200.00, $item->unit_price);
        $this->assertEquals(250.00, $item->original_unit_price);
        $this->assertEquals(50.00, $item->discount_amount);
        $this->assertEquals($deal->id, $item->deal_id);
        $this->assertEquals(0.0, $item->discount);
    }

    public function test_cancelled_or_stale_deal_id_falls_back_to_client_discount(): void
    {
        [$product, $variant] = $this->makeProduct(1000);

        $deal = app(DealService::class)->create([
            'title'         => 'Stale Deal',
            'slug'          => 'stale-deal',
            'discount_type' => Deal::TYPE_PERCENTAGE,
            'discount_value' => 50,
            'starts_at'     => now()->subHour(),
            'status'        => Deal::STATUS_ACTIVE,
        ], [$product->id]);
        app(DealService::class)->cancel($deal);

        // A stale deal_id pointing at a cancelled deal must NOT apply deal pricing.
        $order = app(OrderService::class)->create([
            'customer_id'   => User::factory()->create()->id,
            'order_date'    => now()->toDateString(),
            'status'        => 'confirmed',
            'delivery_method' => 'delivery',
            'items'         => [[
                'product_id'         => $product->id,
                'product_variant_id' => $variant->id,
                'quantity'           => 1,
                'unit_price'         => 1000,
                'original_unit_price'=> 1000,
                'discount'           => 10,
                'discount_amount'    => 100,
                'deal_id'            => $deal->id,
            ]],
        ]);

        $item = $order->items()->first();
        $this->assertEquals(1000.00, $item->unit_price);
        $this->assertEquals(10.0, $item->discount);
        $this->assertNull($item->deal_id);
    }

    private function createCappedDeal(int $productId, int $maxUses, int $initialUses = 0): Deal
    {
        return app(DealService::class)->create([
            'title'         => 'Capped Deal',
            'slug'          => 'capped-' . uniqid(),
            'discount_type' => Deal::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'starts_at'     => now()->subHour(),
            'ends_at'       => now()->addDay(),
            'status'        => Deal::STATUS_ACTIVE,
            'max_uses'      => $maxUses,
            'current_uses'  => $initialUses,
        ], [$productId]);
    }

    private function placeOrder(int $productId, int $variantId, ?int $dealId): Order
    {
        return app(OrderService::class)->create([
            'customer_id'     => User::factory()->create()->id,
            'order_date'      => now()->toDateString(),
            'status'          => 'confirmed',
            'delivery_method' => 'delivery',
            'items'           => [[
                'product_id'          => $productId,
                'product_variant_id'  => $variantId,
                'quantity'            => 1,
                'unit_price'          => 100,
                'original_unit_price' => 100,
                'discount'            => 0,
                'discount_amount'     => 0,
                'deal_id'             => $dealId,
            ]],
        ]);
    }

    public function test_exhausted_deal_is_not_applied_to_pricing(): void
    {
        [$product, $variant] = $this->makeProduct(1000);

        $deal = $this->createCappedDeal($product->id, maxUses: 1, initialUses: 1);

        // Cap reached -> pricing falls back to the static discount path.
        $pricing = app(DealService::class)->priceForVariant($variant);
        $this->assertNull($pricing['deal_id']);
        $this->assertEquals(0.0, $pricing['discount']);
        $this->assertEquals(1000.00, $pricing['net_unit']);

        $this->assertNull(app(DealService::class)->activeDealForProduct($product));
    }

    public function test_deal_usage_increments_on_order_placement(): void
    {
        [$product, $variant] = $this->makeProduct(100);

        $deal = $this->createCappedDeal($product->id, maxUses: 3);

        $this->placeOrder($product->id, $variant->id, $deal->id);

        $this->assertEquals(1, $deal->fresh()->current_uses);
        $this->assertEquals(3, $deal->fresh()->max_uses);
    }

    public function test_order_placement_falls_back_when_cap_reached(): void
    {
        [$product, $variant] = $this->makeProduct(100);

        $deal = $this->createCappedDeal($product->id, maxUses: 1);

        $this->placeOrder($product->id, $variant->id, $deal->id);
        $this->assertEquals(1, $deal->fresh()->current_uses);

        // Second order with the same capped deal: no deal pricing, no throw,
        // no double-count. It degrades to the client-supplied discount.
        $order = $this->placeOrder($product->id, $variant->id, $deal->id);

        $item = $order->items()->first();
        $this->assertNull($item->deal_id);
        $this->assertEquals(100.00, $item->unit_price);
        $this->assertEquals(1, $deal->fresh()->current_uses);
        $this->assertEquals(2, Order::count());
    }

    public function test_consume_usage_guard_blocks_oversell_atomically(): void
    {
        [$product] = $this->makeProduct(100);

        $deal = $this->createCappedDeal($product->id, maxUses: 1);

        $this->assertTrue(app(DealService::class)->consumeUsage($deal));
        $this->assertFalse(app(DealService::class)->consumeUsage($deal));
        $this->assertEquals(1, $deal->fresh()->current_uses);
    }

    public function test_cancel_releases_deal_usage(): void
    {
        [$product, $variant] = $this->makeProduct(100);

        $deal = $this->createCappedDeal($product->id, maxUses: 2);

        $order = $this->placeOrder($product->id, $variant->id, $deal->id);
        $this->assertEquals(1, $deal->fresh()->current_uses);

        app(OrderService::class)->cancel($order);

        $this->assertEquals(0, $deal->fresh()->current_uses);
    }
}