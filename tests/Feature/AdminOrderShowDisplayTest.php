<?php

namespace Tests\Feature;

use App\Models\DeliveryAgent;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Tests\TestCase;

class AdminOrderShowDisplayTest extends TestCase
{
    private function makeOrder(array $overrides = []): Order
    {
        $agent = DeliveryAgent::create([
            'name'           => 'Test Agent',
            'account_number' => 'AG' . rand(100, 999),
            'phone'          => '08012345678',
            'is_active'      => true,
        ]);

        return Order::create(array_merge([
            'reference'         => 'ORD-DISP-' . uniqid(),
            'order_date'        => now()->toDateString(),
            'status'            => 'confirmed',
            'delivery_method'   => 'delivery',
            'delivery_status'   => Order::DELIVERY_STATUS_PENDING,
            'delivery_agent_id' => $agent->id,
            'delivery_address'  => '12 Test Street, Lagos',
            'total_amount'      => 1000,
            'grand_total'       => 1000,
            'amount_paid'       => 0,
        ], $overrides));
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    public function test_order_items_render_product_thumbnail(): void
    {
        $order = $this->makeOrder();
        $product = Product::create([
            'sku'           => 'DISPTHUMB1',
            'name'          => 'Thumb Product',
            'slug'          => 'thumb-product',
            'selling_price' => 100,
            'image'         => 'images/products/thumb.jpg',
        ]);
        OrderItem::create([
            'order_id'           => $order->id,
            'product_id'         => $product->id,
            'quantity'           => 1,
            'unit_price'         => 100,
            'original_unit_price' => 100,
            'discount'           => 0,
            'line_total'         => 100,
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('images/products/thumb.jpg')
            ->assertSee('Thumb Product');
    }

    public function test_order_item_without_image_shows_placeholder(): void
    {
        $order = $this->makeOrder();
        $product = Product::create([
            'sku'           => 'DISPNOIMG1',
            'name'          => 'No Image Product',
            'slug'          => 'no-image-product',
            'selling_price' => 100,
        ]);
        OrderItem::create([
            'order_id'           => $order->id,
            'product_id'         => $product->id,
            'quantity'           => 1,
            'unit_price'         => 100,
            'original_unit_price' => 100,
            'discount'           => 0,
            'line_total'         => 100,
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('bi-image')
            ->assertDontSee('images/products/');
    }

    public function test_guest_order_shows_guest_badge(): void
    {
        $order = $this->makeOrder([
            'customer_id' => null,
            'guest_name'  => 'Walk-in Customer',
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Walk-in Customer')
            ->assertSee('Guest');
    }

    public function test_registered_order_shows_registered_badge(): void
    {
        $user = User::factory()->create(['name' => 'Jane Member']);
        $order = $this->makeOrder([
            'customer_id' => $user->id,
            'guest_name'  => null,
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Jane Member')
            ->assertSee('Registered')
            ->assertDontSee('>Guest<');
    }
}
