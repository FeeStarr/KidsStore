<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Inventory;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CartService;
use App\Services\CouponService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CouponCheckoutTest extends TestCase
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
            'code'          => 'SAVE10',
            'name'          => 'Save 10',
            'discount_type' => Coupon::TYPE_PERCENTAGE,
            'discount_value' => 10,
            'applies_to'    => Coupon::APPLIES_ALL,
            'starts_at'     => now()->subDay(),
            'ends_at'       => now()->addDays(2),
            'status'        => Coupon::STATUS_ACTIVE,
        ], $overrides));
    }

    public function test_guest_cart_coupon_apply_and_remove(): void
    {
        [$product, $variant] = $this->makeProduct(1000);
        $coupon = $this->makeCoupon();

        app(CartService::class)->add($variant->id, 1);

        $applied = app(CartService::class)->applyCoupon('sAVE10');
        $this->assertTrue($applied->is($coupon));
        $this->assertEquals($coupon->id, app(CartService::class)->couponId());
        $this->assertEquals(100.00, app(CartService::class)->couponDiscount());

        app(CartService::class)->removeCoupon();
        $this->assertNull(app(CartService::class)->couponId());
        $this->assertEquals(0.0, app(CartService::class)->couponDiscount());
    }

    public function test_invalid_coupon_code_is_rejected(): void
    {
        [$product, $variant] = $this->makeProduct(1000);
        app(CartService::class)->add($variant->id, 1);

        $this->expectException(ValidationException::class);
        app(CartService::class)->applyCoupon('NOTREAL');
    }

    public function test_coupon_is_carried_into_database_cart_on_login_merge(): void
    {
        [$product, $variant] = $this->makeProduct(1000);
        $coupon = $this->makeCoupon();
        $user = User::factory()->create();

        app(CartService::class)->add($variant->id, 1);
        app(CartService::class)->applyCoupon('SAVE10');

        $this->actingAs($user);
        app(CartService::class)->mergeSessionIntoDatabase();

        $this->assertEquals($coupon->id, app(CartService::class)->couponId());
        $this->assertEquals(100.00, app(CartService::class)->couponDiscount());
    }

    public function test_cart_drops_coupon_when_items_change_below_minimum_order(): void
    {
        [$product, $variant] = $this->makeProduct(500);
        $coupon = $this->makeCoupon(['discount_value' => 50, 'minimum_order_amount' => 1000]);
        $user = User::factory()->create();

        $this->actingAs($user);
        app(CartService::class)->add($variant->id, 3); // 1500 >= 1000
        app(CartService::class)->applyCoupon('SAVE10');
        $this->assertEquals($coupon->id, app(CartService::class)->couponId());

        app(CartService::class)->update($variant->id, 1); // 500 < 1000

        // Coupon no longer meets the minimum and is auto-dropped.
        $this->assertNull(app(CartService::class)->couponId());
    }

    public function test_checkout_places_order_with_coupon_and_records_usage(): void
    {
        [$product, $variant] = $this->makeProduct(2000);
        $coupon = $this->makeCoupon(['discount_value' => 10]);
        $user = User::factory()->create();

        $this->actingAs($user);
        app(CartService::class)->add($variant->id, 1);
        app(CartService::class)->applyCoupon('SAVE10');

        $response = $this->post(route('shop.checkout.place'), [
            'delivery_method' => 'delivery',
            'phone'           => '08012345678',
            'address'         => '12 Test Street',
            'note'            => null,
        ]);

        $response->assertSessionHas('success');

        $order = $user->orders()->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertEquals('confirmed', $order->status);

        $item = $order->items()->first();
        $this->assertNotNull($item);
        $this->assertEquals($coupon->id, $item->coupon_id);
        $this->assertEquals(200.00, (float) $item->coupon_discount);
        $this->assertEquals(1800.00, (float) $order->grand_total);

        $this->assertEquals(1, $coupon->fresh()->usage_count);

        // Coupon and cart cleared after order placement.
        $this->assertNull(app(CartService::class)->couponId());
        $this->assertEquals(0, app(CartService::class)->count());
    }

    public function test_pending_payment_order_counts_usage_only_after_confirmation(): void
    {
        [$product, $variant] = $this->makeProduct(2000);
        $coupon = $this->makeCoupon(['discount_value' => 10]);
        $user = User::factory()->create();
        PaymentMethod::create(['key' => 'instant_bank_transfer', 'label' => 'Instant Bank Transfer', 'is_active' => true]);

        $this->actingAs($user);
        app(CartService::class)->add($variant->id, 1);
        app(CartService::class)->applyCoupon('SAVE10');

        $this->post(route('shop.checkout.place'), [
            'delivery_method' => 'delivery',
            'phone'           => '08012345678',
            'address'         => '12 Test Street',
            'payment_method'  => 'instant_bank_transfer',
        ])->assertSessionHas('success');

        $order = $user->orders()->latest('id')->first();
        $this->assertEquals('pending payment', $order->status);
        $this->assertEquals(200.00, (float) $order->items()->first()->coupon_discount);
        $this->assertEquals(0, $coupon->fresh()->usage_count);

        // Payment verified → usage now counted.
        app(OrderService::class)->confirm($order);
        $this->assertEquals(1, $coupon->fresh()->usage_count);
        $this->assertEquals(1, $coupon->usages()->where('order_id', $order->id)->count());
    }

    public function test_coupon_http_routes(): void
    {
        [$product, $variant] = $this->makeProduct(1000);
        $coupon = $this->makeCoupon();
        $user = User::factory()->create();

        $this->actingAs($user);
        app(CartService::class)->add($variant->id, 1);

        $this->post(route('shop.cart.coupon.apply'), ['code' => 'SAVE10'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertEquals($coupon->id, app(CartService::class)->couponId());

        $this->delete(route('shop.cart.coupon.remove'))
            ->assertRedirect();

        $this->assertNull(app(CartService::class)->couponId());
    }

    public function test_cart_and_checkout_pages_render_with_applied_coupon(): void
    {
        [$product, $variant] = $this->makeProduct(1000);
        $coupon = $this->makeCoupon();
        $user = User::factory()->create();

        $this->actingAs($user);
        app(CartService::class)->add($variant->id, 1);
        app(CartService::class)->applyCoupon('SAVE10');

        $this->get(route('shop.cart.index'))
            ->assertOk()
            ->assertSee('SAVE10')
            ->assertSee('Coupon');;

        $this->get(route('shop.checkout.show'))
            ->assertOk()
            ->assertSee('SAVE10')
            ->assertSee('-&#8358;100.00', false);
    }
}