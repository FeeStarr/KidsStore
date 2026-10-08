<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSidebarBadgeTest extends TestCase
{
    use RefreshDatabase;

    private const SIDEBAR_BADGE = '<span class="badge bg-danger ms-1">%d</span>';

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function makeOrder(string $status): Order
    {
        return Order::create([
            'reference'         => 'ORD-' . uniqid(),
            'order_date'        => now()->toDateString(),
            'status'            => $status,
            'delivery_method'   => 'delivery',
            'delivery_address'  => '12 Test Street, Lagos',
            'total_amount'      => 1000,
            'grand_total'       => 1000,
            'amount_paid'       => 1000,
        ]);
    }

    public function test_orders_sidebar_badge_counts_orders_awaiting_confirmation(): void
    {
        $admin = $this->admin();

        // No new orders -> no badge on the sidebar link.
        $this->actingAs($admin, 'admin')
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertDontSee('bg-danger ms-1');

        $this->makeOrder('pending confirmation');
        $this->makeOrder('pending payment');
        $this->makeOrder('ordered');
        // Already actioned -> must not be counted.
        $this->makeOrder('confirmed');
        $this->makeOrder('delivered');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSee(sprintf(self::SIDEBAR_BADGE, 3), false);
    }

    public function test_requests_sidebar_badge_counts_submitted_and_under_review(): void
    {
        $admin = $this->admin();

        // No requests yet -> no badge on the sidebar link.
        $this->actingAs($admin, 'admin')
            ->get(route('admin.custom-orders.index'))
            ->assertOk()
            ->assertDontSee('bg-danger ms-1');

        CustomOrder::factory()->create(['status' => CustomOrder::STATUS_SUBMITTED]);
        CustomOrder::factory()->create(['status' => CustomOrder::STATUS_UNDER_REVIEW]);
        // Already actioned -> must not be counted.
        CustomOrder::factory()->create(['status' => CustomOrder::STATUS_QUOTED]);
        CustomOrder::factory()->create(['status' => CustomOrder::STATUS_COMPLETED]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.custom-orders.index'))
            ->assertOk()
            ->assertSee(sprintf(self::SIDEBAR_BADGE, 2), false);
    }
}
