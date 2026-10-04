<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\CustomOrderQuote;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CustomOrderPaymentEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): User
    {
        return User::factory()->create(['role' => User::ROLE_CUSTOMER, 'is_active' => true]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
    }

    private function customOrderFor(User $customer, string $status = CustomOrder::STATUS_QUOTED): CustomOrder
    {
        return CustomOrder::create([
            'custom_order_number' => 'CO-PAY-' . uniqid(),
            'user_id' => $customer->id,
            'item_type' => 'frock',
            'status' => $status,
            'child_name' => 'Amara',
            'total_amount' => 29000,
            'delivery_method' => 'delivery',
            'delivery_address' => '12 Test Street',
            'return_policy_acknowledged' => true,
        ]);
    }

    private function makeOrder(array $overrides = []): Order
    {
        return Order::create(array_merge([
            'reference' => 'ORD-PAY-' . uniqid(),
            'order_date' => now()->toDateTimeString(),
            'status' => 'pending payment',
            'delivery_method' => 'delivery',
            'delivery_address' => '12 Test Street, Lagos',
            'payment_method' => 'pay_now',
            'payment_status' => 'unpaid',
            'subtotal' => 29000,
            'total_amount' => 29000,
            'grand_total' => 29000,
            'amount_paid' => 0,
        ], $overrides));
    }

    private function linkedCustomOrder(CustomOrder $customOrder, array $overrides = []): Order
    {
        return $this->makeOrder(array_merge([
            'customer_id' => $customOrder->user_id,
            'custom_order_id' => $customOrder->id,
        ], $overrides));
    }

    public function test_approve_and_pay_lands_on_order_page_with_pay_now_panel(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $customer = $this->customer();

        $order = CustomOrder::create([
            'custom_order_number' => 'CO-PAY-APP-' . uniqid(),
            'user_id' => $customer->id,
            'item_type' => 'frock',
            'status' => CustomOrder::STATUS_QUOTED,
            'child_name' => 'Amara',
            'total_amount' => 19000,
            'delivery_method' => 'delivery',
            'delivery_address' => '12 Test Street',
            'return_policy_acknowledged' => true,
        ]);

        CustomOrderQuote::create([
            'custom_order_id' => $order->id,
            'version' => 1,
            'base_price' => 12000,
            'total' => 19000,
            'status' => CustomOrderQuote::STATUS_DRAFT,
            'valid_until' => now()->addDays(7),
            'created_by' => $admin->id,
        ]);

        $this->actingAs($customer, 'web')
            ->post(route('shop.custom-frock.approve-quote', $order))
            ->assertRedirect(route('shop.account.orders.show', $order->order));

        $linked = $order->fresh()->order;
        $this->assertNotNull($linked);
        $this->assertSame('pay_now', $linked->payment_method);
        $this->assertTrue($linked->isPayNowEligible());

        $this->actingAs($customer, 'web')
            ->get(route('shop.account.orders.show', $linked))
            ->assertOk()
            ->assertSee('id="pay-now-panel"', false)
            ->assertDontSee('no payment deadline');
    }

    public function test_custom_order_eligibility_ignores_24h_window(): void
    {
        $customer = $this->customer();
        $custom = $this->customOrderFor($customer, CustomOrder::STATUS_CUSTOMER_APPROVED);
        $linked = $this->linkedCustomOrder($custom);
        $linked->forceFill(['created_at' => now()->subHours(48)])->save();

        $this->assertTrue($linked->fresh()->isPayNowEligible());
    }

    public function test_regular_order_loses_eligibility_after_24h(): void
    {
        $customer = $this->customer();
        $order = $this->makeOrder(['customer_id' => $customer->id]);
        $order->forceFill(['created_at' => now()->subHours(48)])->save();

        $this->assertFalse($order->fresh()->isPayNowEligible());
    }

    public function test_expire_job_expires_regular_orders_but_skips_custom_orders(): void
    {
        $customer = $this->customer();
        $custom = $this->customOrderFor($customer, CustomOrder::STATUS_CUSTOMER_APPROVED);
        $linkedCustom = $this->linkedCustomOrder($custom);
        $linkedCustom->forceFill(['created_at' => now()->subHours(48)])->save();

        $linkedRegular = $this->makeOrder(['customer_id' => $customer->id]);
        $linkedRegular->forceFill(['created_at' => now()->subHours(48)])->save();

        $this->artisan('payments:expire-pending')->assertSuccessful();

        $this->assertSame('pending payment', $linkedCustom->fresh()->status);
        $this->assertSame('expired', $linkedRegular->fresh()->status);
    }

    public function test_my_orders_shows_pay_now_button_and_custom_badge(): void
    {
        Notification::fake();
        $customer = $this->customer();
        $custom = $this->customOrderFor($customer, CustomOrder::STATUS_CUSTOMER_APPROVED);
        $this->linkedCustomOrder($custom);

        $this->actingAs($customer, 'web')
            ->get(route('shop.account.orders.index'))
            ->assertOk()
            ->assertSee('Pay Now')
            ->assertSee('>Custom</span>', false);
    }

    public function test_my_orders_hides_pay_now_for_paid_orders(): void
    {
        $customer = $this->customer();
        $custom = $this->customOrderFor($customer, CustomOrder::STATUS_PAID);
        $this->linkedCustomOrder($custom, [
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'amount_paid' => 29000,
        ]);

        $this->actingAs($customer, 'web')
            ->get(route('shop.account.orders.index'))
            ->assertOk()
            ->assertDontSee('>Pay Now<', false);
    }

    public function test_custom_frock_show_offers_complete_payment_until_paid(): void
    {
        Notification::fake();
        $customer = $this->customer();
        $custom = $this->customOrderFor($customer, CustomOrder::STATUS_CUSTOMER_APPROVED);
        $linked = $this->linkedCustomOrder($custom);

        $this->actingAs($customer, 'web')
            ->get(route('shop.custom-frock.show', $custom))
            ->assertOk()
            ->assertSee('Complete Payment');

        $linked->update(['status' => 'confirmed', 'payment_status' => 'paid', 'amount_paid' => 29000]);
        $custom->update(['status' => CustomOrder::STATUS_PAID, 'payment_status' => 'paid', 'amount_paid' => 29000]);

        $this->actingAs($customer, 'web')
            ->get(route('shop.custom-frock.show', $custom))
            ->assertOk()
            ->assertDontSee('Complete Payment');
    }

    public function test_order_page_shows_24h_deadline_for_regular_orders(): void
    {
        $customer = $this->customer();
        $order = $this->makeOrder(['customer_id' => $customer->id]);

        $this->actingAs($customer, 'web')
            ->get(route('shop.account.orders.show', $order))
            ->assertOk()
            ->assertSee('Payment required within 24 hours');
    }

    public function test_admin_orders_index_shows_order_type_column(): void
    {
        $customer = $this->customer();
        $custom = $this->customOrderFor($customer, CustomOrder::STATUS_CUSTOMER_APPROVED);
        $this->linkedCustomOrder($custom);
        $this->makeOrder(['customer_id' => $customer->id]);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertSee('>Type<', false)
            ->assertSee('>Custom</span>', false)
            ->assertSee('>Standard</span>', false);
    }

    public function test_backfill_migration_converts_legacy_paystack_custom_orders(): void
    {
        $customer = $this->customer();
        $custom = $this->customOrderFor($customer, CustomOrder::STATUS_CUSTOMER_APPROVED);
        $legacy = $this->linkedCustomOrder($custom, ['payment_method' => 'paystack']);

        $migration = include database_path('migrations/2026_10_04_000003_backfill_custom_order_payment_method.php');
        $migration->up();

        $this->assertSame('pay_now', $legacy->fresh()->payment_method);
    }
}
