<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\DeliveryLocation;
use App\Models\GuestOtp;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CartService;
use App\Services\CouponService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponUsageRegistrationTest extends TestCase
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

    private function makeDeliveryLocation(): DeliveryLocation
    {
        $location = DeliveryLocation::create([
            'name'      => 'Ikeja',
            'state'     => 'Lagos',
            'is_active' => true,
        ]);

        $agent = \App\Models\DeliveryAgent::create([
            'name'           => 'Test Agent',
            'account_number' => 'AG' . rand(10000, 99999),
            'phone'          => '08012345678',
            'is_active'      => true,
        ]);

        \App\Models\DeliveryCharge::create([
            'delivery_agent_id'    => $agent->id,
            'delivery_location_id' => $location->id,
            'amount'               => 0,
            'is_active'            => true,
        ]);

        return $location;
    }

    private function verifyGuest(string $email): void
    {
        GuestOtp::create([
            'email'      => $email,
            'code'       => '123456',
            'expires_at' => now()->addMinutes(10),
            'verified'   => true,
        ]);
    }

    public function test_guest_pay_on_delivery_checkout_registers_coupon_usage(): void
    {
        [$product, $variant] = $this->makeProduct(2000);
        $coupon = $this->makeCoupon();
        $location = $this->makeDeliveryLocation();
        $this->verifyGuest('guest@example.com');

        app(CartService::class)->add($variant->id, 1);
        app(CartService::class)->applyCoupon('SAVE10');

        $this->post(route('shop.checkout.place'), [
            'name'                 => 'Guest Buyer',
            'email'                => 'guest@example.com',
            'delivery_method'      => 'delivery',
            'phone'                => '08012345678',
            'address'              => '12 Test Street',
            'delivery_location_id' => $location->id,
            'payment_method'       => 'pay_on_delivery',
            'note'                 => null,
        ])->assertSessionHas('success');

        $order = Order::whereNull('customer_id')->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertNull($order->customer_id);

        $this->assertEquals(1, $coupon->fresh()->usage_count);
        $usage = CouponUsage::where('order_id', $order->id)->first();
        $this->assertNotNull($usage);
        $this->assertNull($usage->customer_id);
    }

    public function test_guest_pending_payment_usage_is_recorded_on_confirmation(): void
    {
        [$product, $variant] = $this->makeProduct(2000);
        $coupon = $this->makeCoupon();
        $location = $this->makeDeliveryLocation();
        $this->verifyGuest('guest@example.com');

        app(CartService::class)->add($variant->id, 1);
        app(CartService::class)->applyCoupon('SAVE10');

        $this->post(route('shop.checkout.place'), [
            'name'                 => 'Guest Buyer',
            'email'                => 'guest@example.com',
            'delivery_method'      => 'delivery',
            'phone'                => '08012345678',
            'address'              => '12 Test Street',
            'delivery_location_id' => $location->id,
            'payment_method'       => 'pay_now',
            'note'                 => null,
        ])->assertSessionHas('success');

        $order = Order::whereNull('customer_id')->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertEquals('pending payment', $order->status);
        $this->assertEquals(0, $coupon->fresh()->usage_count);

        app(OrderService::class)->confirm($order);

        $this->assertEquals(1, $coupon->fresh()->usage_count);
        $usage = CouponUsage::where('order_id', $order->id)->first();
        $this->assertNotNull($usage);
        $this->assertNull($usage->customer_id);
    }

    public function test_repeat_customer_usage_registers_when_per_customer_limit_allows(): void
    {
        [$product, $variant] = $this->makeProduct(2000);
        $coupon = $this->makeCoupon(['per_customer_limit' => 2]);
        $user = User::factory()->create();
        $location = $this->makeDeliveryLocation();

        $this->actingAs($user);
        app(CartService::class)->add($variant->id, 1);
        app(CartService::class)->applyCoupon('SAVE10');

        $this->post(route('shop.checkout.place'), [
            'delivery_method'      => 'delivery',
            'phone'                => '08012345678',
            'address'              => '12 Test Street',
            'delivery_location_id' => $location->id,
            'payment_method'       => 'pay_on_delivery',
            'note'                 => null,
        ])->assertSessionHas('success');

        $first = $user->orders()->latest('id')->first();
        $this->assertEquals(1, $coupon->fresh()->usage_count);
        $this->assertEquals(1, CouponUsage::where('order_id', $first->id)->count());

        // Same customer reuses the coupon within their per-customer limit.
        app(CartService::class)->add($variant->id, 1);
        app(CartService::class)->applyCoupon('SAVE10');

        $this->post(route('shop.checkout.place'), [
            'delivery_method'      => 'delivery',
            'phone'                => '08012345678',
            'address'              => '12 Test Street',
            'delivery_location_id' => $location->id,
            'payment_method'       => 'pay_on_delivery',
            'note'                 => null,
        ])->assertSessionHas('success');

        $second = $user->orders()->latest('id')->first();
        $this->assertNotEquals($first->id, $second->id);
        $this->assertEquals(2, $coupon->fresh()->usage_count);
        $this->assertEquals(2, CouponUsage::where('coupon_id', $coupon->id)->count());
        $this->assertEquals(1, CouponUsage::where('order_id', $second->id)->count());
    }
}
