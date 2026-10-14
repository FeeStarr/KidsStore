<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\User;
use App\Services\CustomPaymentService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The orders table carries both grand_total and a legacy total_amount. The custom
 * order flow only ever wrote grand_total, so total_amount stayed 0 - and every money
 * figure derived from it reported nonsense, including a fully paid order showing a
 * balance of -15,000.
 */
class CustomOrderTotalsIntegrityTest extends TestCase
{
    private function customer(): User
    {
        return User::factory()->create(['role' => User::ROLE_CUSTOMER, 'is_active' => true]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
    }

    private function customOrder(User $customer, float $total = 15000.0): CustomOrder
    {
        return CustomOrder::create([
            'custom_order_number' => 'CO-TOT-'.uniqid(),
            'user_id' => $customer->id,
            'item_type' => 'frock',
            'status' => CustomOrder::STATUS_PAYMENT_PENDING,
            'child_name' => 'Amara',
            'total_amount' => $total,
            'delivery_method' => 'delivery',
            'delivery_address' => '12 Test Street',
            'return_policy_acknowledged' => true,
        ]);
    }

    public function test_linked_order_records_the_total_amount_as_well_as_grand_total(): void
    {
        $customer = $this->customer();
        $order = $this->customOrder($customer, 15000.0);

        $linked = app(CustomPaymentService::class)->createLinkedOrder($order);

        $this->assertSame(15000.0, (float) $linked->fresh()->grand_total);
        $this->assertSame(
            15000.0,
            (float) $linked->fresh()->total_amount,
            'total_amount must be written for linked custom orders or money derived from it is wrong.'
        );
    }

    /**
     * The reported defect: a paid custom order rendered a balance of -15,000.
     */
    public function test_fully_paid_custom_order_reports_a_zero_balance(): void
    {
        $customer = $this->customer();
        $order = $this->customOrder($customer, 15000.0);

        $linked = app(CustomPaymentService::class)->createLinkedOrder($order);

        // Reproduce the historical broken state explicitly: the legacy column is 0
        // while the order has genuinely been paid in full.
        $linked->forceFill([
            'total_amount' => 0,
            'amount_paid' => 15000.0,
        ])->save();

        $this->assertSame(0.0, (float) $linked->fresh()->balance);
    }

    public function test_partially_paid_custom_order_reports_the_remainder(): void
    {
        $customer = $this->customer();
        $order = $this->customOrder($customer, 15000.0);

        $linked = app(CustomPaymentService::class)->createLinkedOrder($order);
        $linked->forceFill(['total_amount' => 0, 'amount_paid' => 5000.0])->save();

        $this->assertSame(10000.0, (float) $linked->fresh()->balance);
    }

    public function test_unpaid_custom_order_reports_the_full_amount_owed(): void
    {
        $customer = $this->customer();
        $order = $this->customOrder($customer, 15000.0);

        $linked = app(CustomPaymentService::class)->createLinkedOrder($order);
        $linked->forceFill(['total_amount' => 0, 'amount_paid' => 0.0])->save();

        $this->assertSame(15000.0, (float) $linked->fresh()->balance);
    }

    /**
     * COALESCE(total_amount, grand_total) never fell back, because total_amount is
     * NOT NULL DEFAULT 0.00 - so custom orders contributed nothing at all.
     */
    public function test_dashboard_orders_total_includes_custom_orders(): void
    {
        $customer = $this->customer();
        $admin = $this->admin();

        $order = $this->customOrder($customer, 15000.0);
        $linked = app(CustomPaymentService::class)->createLinkedOrder($order);
        $linked->forceFill(['total_amount' => 0])->save();

        $total = (float) DB::table('orders')
            ->selectRaw('COALESCE(NULLIF(total_amount, 0), grand_total) as payable')
            ->where('customer_id', $customer->id)
            ->value('payable');

        $this->assertSame(15000.0, $total);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'));
        $response->assertOk();
        $response->assertSee('15,000.00');
    }

    /**
     * The backfill is deliberately narrow so it cannot overwrite a real total.
     */
    public function test_backfill_only_aligns_rows_where_total_amount_is_missing(): void
    {
        $customer = $this->customer();

        $missing = $this->customOrder($customer, 15000.0);
        $linked = app(CustomPaymentService::class)->createLinkedOrder($missing);
        $linked->forceFill(['total_amount' => 0])->save();

        $intact = $this->customOrder($customer, 7000.0);
        $intactLinked = app(CustomPaymentService::class)->createLinkedOrder($intact);
        $intactLinked->forceFill([
            'grand_total' => 7000.0,
            'total_amount' => 7000.0,
        ])->save();

        // Run the migration's update statement verbatim.
        DB::table('orders')
            ->where('total_amount', 0)
            ->where('grand_total', '>', 0)
            ->update(['total_amount' => DB::raw('grand_total')]);

        $this->assertSame(15000.0, (float) $linked->fresh()->total_amount);
        $this->assertSame(7000.0, (float) $intactLinked->fresh()->total_amount);
    }
}
