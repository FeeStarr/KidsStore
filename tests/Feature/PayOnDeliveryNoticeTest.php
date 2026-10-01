<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayOnDeliveryNoticeTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_pay_on_delivery_option_states_items_released_after_payment(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $product = Product::create([
            'sku'           => 'PODNOTICE1',
            'name'          => 'POD Notice Product',
            'slug'          => 'pod-notice-product',
            'selling_price' => 100,
        ]);
        $variant = ProductVariant::create([
            'product_id'    => $product->id,
            'sku'           => 'PODNOTICE1-V1',
            'name'          => 'Default',
            'selling_price' => 100,
            'discount'      => 0,
        ]);
        $this->app->make(CartService::class)->add($variant->id, 1);

        $this->get(route('shop.checkout.show'))
            ->assertOk()
            ->assertSee('Items will only be released to you by the delivery agent or at the pickup station after payment', false);
    }

    public function test_track_page_pay_at_door_alert_states_items_released_after_payment(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $order = Order::create([
            'reference'        => 'ORD-PODNOTICE-' . uniqid(),
            'lookup_token'     => 'pod-notice-' . uniqid(),
            'order_date'       => now()->toDateString(),
            'customer_id'      => $customer->id,
            'status'           => 'confirmed',
            'delivery_method'  => 'delivery',
            'payment_method'   => 'pay_on_delivery',
            'payment_status'   => 'unpaid',
            'delivery_address' => '12 Test Street, Lagos',
            'total_amount'     => 1000,
            'grand_total'      => 1000,
            'amount_paid'      => 0,
        ]);

        $product = Product::create([
            'sku'           => 'PODNOTICE2',
            'name'          => 'POD Notice Item',
            'slug'          => 'pod-notice-item',
            'selling_price' => 100,
        ]);
        OrderItem::create([
            'order_id'            => $order->id,
            'product_id'          => $product->id,
            'quantity'            => 1,
            'unit_price'          => 100,
            'original_unit_price' => 100,
            'discount'            => 0,
            'line_total'          => 100,
        ]);

        $this->actingAs($customer)
            ->get(route('shop.order.track', $order->lookup_token))
            ->assertOk()
            ->assertSee('by the delivery agent or at the pickup station', false);
    }
}
