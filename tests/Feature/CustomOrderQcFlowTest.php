<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\User;
use App\Services\CustomOrderService;
use Tests\TestCase;

class CustomOrderQcFlowTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    public function test_rework_can_be_resumed_and_resubmitted_for_qc(): void
    {
        $service = app(CustomOrderService::class);
        $order = CustomOrder::factory()->create(['status' => CustomOrder::STATUS_QUALITY_CHECK]);

        $service->failQc($order);
        $this->assertSame(CustomOrder::STATUS_REWORK_REQUIRED, $order->fresh()->status);

        $service->resumeProduction($order->fresh());
        $this->assertSame(CustomOrder::STATUS_IN_PRODUCTION, $order->fresh()->status);

        $service->submitForQc($order->fresh());
        $this->assertSame(CustomOrder::STATUS_QUALITY_CHECK, $order->fresh()->status);
        $this->assertCount(count(CustomOrderService::QC_CHECK_ITEMS), $order->qcChecks()->get());
    }

    public function test_staff_can_resume_rework_from_admin_route(): void
    {
        $order = CustomOrder::factory()->create(['status' => CustomOrder::STATUS_REWORK_REQUIRED]);

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.custom-orders.resume-production', $order))
            ->assertRedirect();

        $this->assertSame(CustomOrder::STATUS_IN_PRODUCTION, $order->fresh()->status);
    }

    public function test_customer_cannot_resume_rework(): void
    {
        $order = CustomOrder::factory()->create(['status' => CustomOrder::STATUS_REWORK_REQUIRED]);

        $this->actingAsCustomer();

        $this->post(route('admin.custom-orders.resume-production', $order))
            ->assertRedirect(route('admin.login'));

        $this->assertSame(CustomOrder::STATUS_REWORK_REQUIRED, $order->fresh()->status);
    }

    public function test_show_page_offers_resume_only_for_rework_orders(): void
    {
        $rework = CustomOrder::factory()->create(['status' => CustomOrder::STATUS_REWORK_REQUIRED]);
        $qc = CustomOrder::factory()->create(['status' => CustomOrder::STATUS_QUALITY_CHECK]);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.custom-orders.show', $rework))
            ->assertOk()
            ->assertSee('Resume Production');

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.custom-orders.show', $qc))
            ->assertOk()
            ->assertDontSee('Resume Production');
    }
}
