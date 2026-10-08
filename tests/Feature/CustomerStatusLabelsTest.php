<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\User;
use Tests\TestCase;

class CustomerStatusLabelsTest extends TestCase
{
    public function test_customer_status_label_accessor_uses_friendly_wording(): void
    {
        $cases = [
            CustomOrder::STATUS_IN_PRODUCTION => 'Being Made',
            CustomOrder::STATUS_QUALITY_CHECK => 'Quality Check in Progress',
            CustomOrder::STATUS_REWORK_REQUIRED => 'Being Finalized',
            CustomOrder::STATUS_READY_FOR_DELIVERY => 'Ready for Delivery',
            CustomOrder::STATUS_READY_FOR_PICKUP => 'Ready for Pickup',
        ];

        foreach ($cases as $status => $expected) {
            $order = new CustomOrder();
            $order->status = $status;

            $this->assertSame($expected, $order->customer_status_label);
        }
    }

    public function test_internal_status_label_is_unchanged_for_admins(): void
    {
        $order = new CustomOrder();
        $order->status = CustomOrder::STATUS_QUALITY_CHECK;

        $this->assertSame('Quality Check', $order->status_label);
    }

    public function test_shop_show_page_shows_customer_wording(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $order = CustomOrder::factory()->create([
            'user_id' => $user->id,
            'status' => CustomOrder::STATUS_QUALITY_CHECK,
        ]);

        $this->actingAs($user)
            ->get(route('shop.custom-frock.show', $order))
            ->assertOk()
            ->assertSee('Quality Check in Progress');
    }

    public function test_admin_show_page_keeps_internal_wording(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $order = CustomOrder::factory()->create(['status' => CustomOrder::STATUS_QUALITY_CHECK]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.custom-orders.show', $order))
            ->assertOk()
            ->assertSee('Quality Check')
            ->assertDontSee('Quality Check in Progress');
    }
}
