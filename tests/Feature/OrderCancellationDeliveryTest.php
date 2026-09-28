<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\OrderController;
use App\Models\DeliveryAgent;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use Tests\TestCase;

class OrderCancellationDeliveryTest extends TestCase
{
    private function makeAgent(): DeliveryAgent
    {
        return DeliveryAgent::create([
            'name'           => 'Test Agent',
            'account_number' => 'AG' . rand(100, 999),
            'phone'          => '08012345678',
            'is_active'      => true,
        ]);
    }

    private function makeOrder(array $overrides = []): Order
    {
        $agent = $this->makeAgent();

        return Order::create(array_merge([
            'reference'         => 'ORD-TEST-' . uniqid(),
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

    public function test_cancel_sets_delivery_status_to_cancelled(): void
    {
        $order = $this->makeOrder();

        app(OrderService::class)->cancel($order);

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertSame(Order::DELIVERY_STATUS_CANCELLED, $order->delivery_status);
    }

    public function test_cancel_keeps_delivered_delivery_status(): void
    {
        $order = $this->makeOrder([
            'delivery_status'      => Order::DELIVERY_STATUS_DELIVERED,
            'delivery_delivered_at' => now(),
        ]);

        app(OrderService::class)->cancel($order);

        $this->assertSame('cancelled', $order->refresh()->status);
        $this->assertSame(Order::DELIVERY_STATUS_DELIVERED, $order->delivery_status);
    }

    public function test_cancel_pickup_order_keeps_delivery_status_null(): void
    {
        $order = $this->makeOrder([
            'delivery_method'   => 'pickup',
            'delivery_status'   => null,
            'delivery_agent_id' => null,
        ]);

        app(OrderService::class)->cancel($order);

        $this->assertSame('cancelled', $order->refresh()->status);
        $this->assertNull($order->delivery_status);
    }

    public function test_cancelled_order_hides_approve_and_reassign_buttons(): void
    {
        $admin = $this->admin();
        $order = $this->makeOrder();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Approve Delivery')
            ->assertSee('Reassign Agent');

        app(OrderService::class)->cancel($order);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.orders.show', $order->refresh()))
            ->assertOk()
            ->assertSee('Cancelled')
            ->assertDontSee('Approve Delivery')
            ->assertDontSee('Reassign Agent');
    }

    public function test_approve_delivery_rejected_on_legacy_cancelled_order(): void
    {
        $order = $this->makeOrder([
            'status'          => 'cancelled',
            'delivery_status' => Order::DELIVERY_STATUS_PENDING,
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.orders.approve-delivery', $order))
            ->assertStatus(400);
    }

    public function test_reassign_agent_rejected_on_legacy_cancelled_order(): void
    {
        $order = $this->makeOrder([
            'status'          => 'cancelled',
            'delivery_status' => Order::DELIVERY_STATUS_PENDING,
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.orders.reassign-agent', $order), [
                'delivery_agent_id' => $order->delivery_agent_id,
            ])
            ->assertStatus(400);
    }
}
