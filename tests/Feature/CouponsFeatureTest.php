<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CartService;
use App\Services\CouponService;
use App\Services\DealService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class CouponsFeatureTest extends TestCase
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

    private function makeCoupon(array $overrides = []): Coupon
    {
        return app(CouponService::class)->create(array_merge([
            'code'          => 'KIDS500',
            'name'          => 'Kids 500',
            'discount_type' => Coupon::TYPE_FIXED_AMOUNT,
            'discount_value' => 500,
            'applies_to'    => Coupon::APPLIES_ALL,
            'starts_at'     => now()->subDay(),
            'ends_at'       => now()->addDays(2),
            'status'        => Coupon::STATUS_ACTIVE,
        ], $overrides));
    }

    private function hydrateCart(int $variantId, int $qty = 1): \Illuminate\Support\Collection
    {
        app(CartService::class)->add($variantId, $qty);

        return app(CartService::class)->items();
    }

    public function test_code_is_normalized_to_lowercase(): void
    {
        $coupon = $this->makeCoupon();

        $this->assertEquals('kids500', $coupon->fresh()->code);
        $this->assertInstanceOf(Coupon::class, app(CouponService::class)->findByCode('KIDS500'));
        $this->assertInstanceOf(Coupon::class, app(CouponService::class)->findByCode('kids500'));
    }

    public function test_percentage_coupon_discount(): void
    {
        $coupon = $this->makeCoupon([
            'discount_type'  => Coupon::TYPE_PERCENTAGE,
            'discount_value' => 10,
        ]);

        [$product, $variant] = $this->makeProduct(1000);
        $items = $this->hydrateCart($variant->id);

        $eligible = app(CouponService::class)->eligibleSubtotal($coupon, $items);
        $this->assertEquals(1000.00, $eligible);
        $this->assertEquals(100.00, app(CouponService::class)->discountFor($coupon, $eligible));
    }

    public function test_fixed_amount_coupon_discount(): void
    {
        $coupon = $this->makeCoupon();

        [$product, $variant] = $this->makeProduct(3000);
        $items = $this->hydrateCart($variant->id);

        $eligible = app(CouponService::class)->eligibleSubtotal($coupon, $items);
        $this->assertEquals(500.00, app(CouponService::class)->discountFor($coupon, $eligible));
    }

    public function test_maximum_discount_amount_caps_coupon(): void
    {
        $coupon = $this->makeCoupon([
            'discount_type'           => Coupon::TYPE_PERCENTAGE,
            'discount_value'          => 50,
            'maximum_discount_amount' => 200,
        ]);

        [$product, $variant] = $this->makeProduct(1000);
        $items = $this->hydrateCart($variant->id);

        $eligible = app(CouponService::class)->eligibleSubtotal($coupon, $items);
        $this->assertEquals(200.00, app(CouponService::class)->discountFor($coupon, $eligible));
    }

    public function test_minimum_order_amount_is_enforced(): void
    {
        $coupon = $this->makeCoupon(['minimum_order_amount' => 5000]);

        [$product, $variant] = $this->makeProduct(1000);
        $items = $this->hydrateCart($variant->id);

        $this->expectException(ValidationException::class);
        app(CouponService::class)->validate($coupon, $items, null);
    }

    public function test_expired_coupon_is_rejected(): void
    {
        $coupon = $this->makeCoupon(['ends_at' => now()->subHour()]);

        [$product, $variant] = $this->makeProduct(1000);
        $items = $this->hydrateCart($variant->id);

        $this->expectException(ValidationException::class);
        app(CouponService::class)->validate($coupon, $items, null);
    }

    public function test_inactive_coupon_is_rejected(): void
    {
        $coupon = $this->makeCoupon(['status' => Coupon::STATUS_INACTIVE]);

        [$product, $variant] = $this->makeProduct(1000);
        $items = $this->hydrateCart($variant->id);

        $this->expectException(ValidationException::class);
        app(CouponService::class)->validate($coupon, $items, null);
    }

    public function test_coupon_targets_specific_product(): void
    {
        [$productA, $variantA] = $this->makeProduct(1000);
        [$productB, $variantB] = $this->makeProduct(2000);

        $coupon = $this->makeCoupon();
        $coupon->products()->sync([$productA->id]);

        app(CartService::class)->add($variantA->id);
        app(CartService::class)->add($variantB->id);
        $items = app(CartService::class)->items();

        $eligible = app(CouponService::class)->eligibleSubtotal($coupon, $items);
        $this->assertEquals(1000.00, $eligible);
    }

    public function test_regular_price_only_excludes_deal_items(): void
    {
        [$product, $variant] = $this->makeProduct(1000);

        app(DealService::class)->create([
            'title'         => 'Live Deal',
            'slug'          => 'live-deal-' . uniqid(),
            'discount_type' => \App\Models\Deal::TYPE_PERCENTAGE,
            'discount_value' => 20,
            'starts_at'     => now()->subHour(),
            'ends_at'       => now()->addDay(),
            'status'        => \App\Models\Deal::STATUS_ACTIVE,
        ], [$product->id]);

        $coupon = $this->makeCoupon(['applies_to' => Coupon::APPLIES_REGULAR_PRICE_ONLY]);

        $items = $this->hydrateCart($variant->id);
        $this->assertNotNull($items->first()->deal_id);

        $eligible = app(CouponService::class)->eligibleSubtotal($coupon, $items);
        $this->assertEquals(0.00, $eligible);
    }

    public function test_per_customer_once_is_enforced(): void
    {
        [$product, $variant] = $this->makeProduct(1000);
        $customer = User::factory()->create();
        $coupon = $this->makeCoupon();

        $orderA = $this->placeOrder($product->id, $variant->id, $coupon, $customer->id);

        $this->assertEquals(1, $coupon->fresh()->usage_count);

        // A second order using the same coupon for the same customer must fail.
        $this->expectException(RuntimeException::class);
        $this->placeOrder($product->id, $variant->id, $coupon, $customer->id);
    }

    public function test_usage_count_increments_per_order(): void
    {
        [$product, $variant] = $this->makeProduct(1000);
        $customerA = User::factory()->create();
        $customerB = User::factory()->create();
        $coupon = $this->makeCoupon(['usage_limit' => 5]);

        $orderA = $this->placeOrder($product->id, $variant->id, $coupon, $customerA->id);
        $this->assertEquals(1, $coupon->fresh()->usage_count);

        $orderB = $this->placeOrder($product->id, $variant->id, $coupon, $customerB->id);
        $this->assertEquals(2, $coupon->fresh()->usage_count);
    }

    public function test_cancel_releases_coupon_usage(): void
    {
        [$product, $variant] = $this->makeProduct(1000);
        $customer = User::factory()->create();
        $coupon = $this->makeCoupon();

        $order = $this->placeOrder($product->id, $variant->id, $coupon, $customer->id);
        $this->assertEquals(1, $coupon->fresh()->usage_count);

        app(OrderService::class)->cancel($order);

        $this->assertEquals(0, $coupon->fresh()->usage_count);
    }

    public function test_variant_level_deal_beats_product_level_deal(): void
    {
        [$product, $variant] = $this->makeProduct(1000);

        $productDeal = app(DealService::class)->create([
            'title'         => 'Product Deal',
            'slug'          => 'p-deal-' . uniqid(),
            'discount_type' => \App\Models\Deal::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'starts_at'     => now()->subHour(),
            'ends_at'       => now()->addDay(),
            'status'        => \App\Models\Deal::STATUS_ACTIVE,
        ], [$product->id]);

        $variantDeal = app(DealService::class)->create([
            'title'         => 'Variant Deal',
            'slug'          => 'v-deal-' . uniqid(),
            'discount_type' => \App\Models\Deal::TYPE_PERCENTAGE,
            'discount_value' => 30,
            'starts_at'     => now()->subHour(),
            'ends_at'       => now()->addDay(),
            'status'        => \App\Models\Deal::STATUS_ACTIVE,
        ], [], [$variant->id]);

        $active = app(DealService::class)->activeDealForVariant($variant);
        $this->assertEquals($variantDeal->id, $active->id);

        $pricing = app(DealService::class)->priceForVariant($variant);
        $this->assertEquals(700.00, $pricing['net_unit']);
    }

    public function test_variant_level_overlap_is_rejected(): void
    {
        [$product, $variant] = $this->makeProduct(1000);

        app(DealService::class)->create([
            'title'         => 'Variant Deal A',
            'slug'          => 'v-overlap-a-' . uniqid(),
            'discount_type' => \App\Models\Deal::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'starts_at'     => now()->subDay(),
            'ends_at'       => now()->addDays(2),
            'status'        => \App\Models\Deal::STATUS_ACTIVE,
        ], [], [$variant->id]);

        $this->expectException(ValidationException::class);

        app(DealService::class)->create([
            'title'         => 'Variant Deal B',
            'slug'          => 'v-overlap-b-' . uniqid(),
            'discount_type' => \App\Models\Deal::TYPE_PERCENTAGE,
            'discount_value' => 15,
            'starts_at'     => now(),
            'ends_at'       => now()->addDay(),
            'status'        => \App\Models\Deal::STATUS_ACTIVE,
        ], [], [$variant->id]);
    }

    public function test_coupon_discount_is_snapshotted_and_subtracted_from_total(): void
    {
        [$product, $variant] = $this->makeProduct(1000);
        $customer = User::factory()->create();
        $coupon = $this->makeCoupon([
            'discount_type'  => Coupon::TYPE_PERCENTAGE,
            'discount_value' => 20,
        ]);

        $order = $this->placeOrder($product->id, $variant->id, $coupon, $customer->id);
        $item = $order->items()->first();

        $this->assertEquals($coupon->id, $item->coupon_id);
        $this->assertEquals(200.00, (float) $item->coupon_discount);
        $this->assertEquals(800.00, (float) $order->fresh()->grand_total);
    }

    public function test_regular_price_only_coupon_skips_deal_items_on_order(): void
    {
        [$dealProduct, $dealVariant] = $this->makeProduct(1000);
        [$regularProduct, $regularVariant] = $this->makeProduct(1000);

        $deal = app(DealService::class)->create([
            'title'         => 'Deal',
            'slug'          => 'deal-' . uniqid(),
            'discount_type' => \App\Models\Deal::TYPE_PERCENTAGE,
            'discount_value' => 20,
            'starts_at'     => now()->subHour(),
            'ends_at'       => now()->addDay(),
            'status'        => \App\Models\Deal::STATUS_ACTIVE,
        ], [$dealProduct->id]);

        $coupon = $this->makeCoupon(['applies_to' => Coupon::APPLIES_REGULAR_PRICE_ONLY]);
        $customer = User::factory()->create();

        // Mix: one deal-priced item + one regular item. The coupon must only
        // apply its discount to the regular item (face value 500).
        $order = app(OrderService::class)->create([
            'customer_id'     => $customer->id,
            'order_date'      => now()->toDateString(),
            'status'          => 'confirmed',
            'delivery_method' => 'delivery',
            'items'           => [
                [
                    'product_id'          => $dealProduct->id,
                    'product_variant_id'  => $dealVariant->id,
                    'quantity'            => 1,
                    'unit_price'          => 1000,
                    'original_unit_price' => 1000,
                    'discount'            => 0,
                    'discount_amount'     => 0,
                    'deal_id'             => $deal->id,
                    'coupon_id'           => $coupon->id,
                ],
                [
                    'product_id'          => $regularProduct->id,
                    'product_variant_id'  => $regularVariant->id,
                    'quantity'            => 1,
                    'unit_price'          => 1000,
                    'original_unit_price' => 1000,
                    'discount'            => 0,
                    'discount_amount'     => 0,
                    'coupon_id'           => $coupon->id,
                ],
            ],
        ]);

        $dealItem = $order->items()->where('deal_id', $deal->id)->first();
        $regularItem = $order->items()->where('deal_id', null)->first();

        $this->assertNotNull($dealItem->deal_id);
        $this->assertEquals(0.00, (float) $dealItem->coupon_discount);
        $this->assertEquals(500.00, (float) $regularItem->coupon_discount);
        // Deal line (800) + regular line (1000) - coupon (500).
        $this->assertEquals(1300.00, (float) $order->fresh()->grand_total);
    }

    private function placeOrder(int $productId, int $variantId, Coupon $coupon, int $customerId, ?int $dealId = null): Order
    {
        $order = app(OrderService::class)->create([
            'customer_id'     => $customerId,
            'order_date'      => now()->toDateString(),
            'status'          => 'confirmed',
            'delivery_method' => 'delivery',
            'items'           => [[
                'product_id'          => $productId,
                'product_variant_id'  => $variantId,
                'quantity'            => 1,
                'unit_price'          => 1000,
                'original_unit_price' => 1000,
                'discount'            => 0,
                'discount_amount'     => 0,
                'deal_id'             => $dealId,
                'coupon_id'           => $coupon->id,
            ]],
        ]);

        return $order;
    }
}