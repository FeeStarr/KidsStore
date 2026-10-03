<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Notifications\OrderPlacedNotification;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PayOnDeliveryOrderNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function placeOrder(User $customer, string $paymentMethod, string $status): Order
    {
        $product = Product::create([
            'sku'           => 'PODNOTIFY-' . uniqid(),
            'name'          => 'POD Notify Product',
            'slug'          => 'pod-notify-' . uniqid(),
            'selling_price' => 100,
        ]);
        $variant = ProductVariant::create([
            'product_id'    => $product->id,
            'sku'           => $product->sku . '-V1',
            'name'          => 'Default',
            'selling_price' => 100,
            'discount'      => 0,
        ]);
        Inventory::create([
            'product_id'         => $product->id,
            'product_variant_id' => $variant->id,
            'quantity'           => 10,
            'reorder_level'      => 5,
        ]);

        return app(OrderService::class)->create([
            'order_date'        => now()->toDateString(),
            'customer_id'       => $customer->id,
            'status'            => $status,
            'payment_method'    => $paymentMethod,
            'delivery_method'   => 'delivery',
            'delivery_address'  => '12 Test Street, Lagos',
            'items'             => [
                ['product_variant_id' => $variant->id, 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);
    }

    public function test_pay_on_delivery_order_is_notified_at_placement(): void
    {
        Notification::fake();
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $order = $this->placeOrder($customer, 'pay_on_delivery', 'pending confirmation');

        $this->assertSame('pending confirmation', $order->status);
        Notification::assertSentTo($customer, OrderPlacedNotification::class);
    }

    public function test_confirming_an_already_notified_order_does_not_send_a_second_email(): void
    {
        Notification::fake();
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $order = $this->placeOrder($customer, 'pay_on_delivery', 'pending confirmation');

        app(OrderService::class)->confirm($order);

        $this->assertSame('confirmed', $order->fresh()->status);
        Notification::assertSentToTimes($customer, OrderPlacedNotification::class, 1);
    }

    public function test_pending_payment_order_is_not_notified_until_payment_is_verified(): void
    {
        Notification::fake();
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $this->placeOrder($customer, 'pay_now', 'pending payment');

        Notification::assertNotSentTo($customer, OrderPlacedNotification::class);
    }
}
