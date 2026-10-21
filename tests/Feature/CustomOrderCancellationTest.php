<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\Order;
use App\Models\RefundRequest;
use App\Models\User;
use App\Notifications\CustomOrderCancelled;
use App\Services\CustomOrderService;
use App\Services\CustomPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Cancelling a custom frock, and keeping the request and the order that pays for it
 * in step.
 *
 * These are one purchase with two records. When they were allowed to drift apart a
 * customer was left with a request that said "payment pending" and an invoice that
 * could no longer be paid, and neither side knew the other had been cancelled.
 */
class CustomOrderCancellationTest extends TestCase
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

    private function request(User $customer, string $status = CustomOrder::STATUS_PAYMENT_PENDING): CustomOrder
    {
        return CustomOrder::create([
            'custom_order_number' => 'CO-CANCEL-'.uniqid(),
            'user_id' => $customer->id,
            'item_type' => 'frock',
            'status' => $status,
            'child_name' => 'Amara',
            'total_amount' => 27000,
            'delivery_method' => 'delivery',
            'delivery_address' => '12 Test Street',
            'return_policy_acknowledged' => true,
            'sizing_method' => CustomOrder::SIZING_STANDARD,
        ]);
    }

    private function linkedOrder(CustomOrder $order): Order
    {
        return app(CustomPaymentService::class)->createLinkedOrder($order);
    }

    // ── the reason ────────────────────────────────────────────────────────

    public function test_a_customer_cancellation_needs_a_reason(): void
    {
        $customer = $this->customer();
        $order = $this->request($customer, CustomOrder::STATUS_QUOTED);

        $this->actingAs($customer, 'web')
            ->post(route('shop.custom-frock.cancel', $order))
            ->assertSessionHasErrors('reason');

        $this->assertNotSame(CustomOrder::STATUS_CANCELLED, $order->fresh()->status);
    }

    public function test_the_reason_is_recorded_on_the_status_history(): void
    {
        Notification::fake();
        $customer = $this->customer();
        $order = $this->request($customer, CustomOrder::STATUS_QUOTED);

        $this->actingAs($customer, 'web')
            ->post(route('shop.custom-frock.cancel', $order), [
                'reason' => 'The size does not suit her any more.',
            ])
            ->assertSessionHas('success');

        $history = $order->fresh()->statusHistory()->latest('id')->first();

        $this->assertSame(CustomOrder::STATUS_CANCELLED, $history->new_status);
        $this->assertSame('The size does not suit her any more.', $history->reason);
    }

    public function test_the_reason_is_shown_on_the_customer_timeline(): void
    {
        Notification::fake();
        $customer = $this->customer();
        $order = $this->request($customer, CustomOrder::STATUS_QUOTED);

        $this->actingAs($customer, 'web')
            ->post(route('shop.custom-frock.cancel', $order), [
                'reason' => 'The size does not suit her any more.',
            ]);

        $this->actingAs($customer, 'web')
            ->get(route('shop.custom-frock.show', $order))
            ->assertOk()
            ->assertSee('The size does not suit her any more.')
            ->assertSee('Cancelled');
    }

    // ── the three paths ────────────────────────────────────────────────────

    public function test_a_customer_can_cancel_before_paying(): void
    {
        Notification::fake();
        $customer = $this->customer();
        $order = $this->request($customer, CustomOrder::STATUS_QUOTED);
        $linked = $this->linkedOrder($order);

        $this->actingAs($customer, 'web')
            ->post(route('shop.custom-frock.cancel', $order), ['reason' => 'Changed our mind.'])
            ->assertSessionHas('success');

        $this->assertSame(CustomOrder::STATUS_CANCELLED, $order->fresh()->status);
        $this->assertSame('cancelled', $linked->fresh()->status);
    }

    public function test_a_customer_cannot_cancel_after_paying(): void
    {
        Notification::fake();
        $customer = $this->customer();
        $order = $this->request($customer, CustomOrder::STATUS_PAID);

        // No button is offered, and the route refuses even if it is called.
        $this->actingAs($customer, 'web')
            ->get(route('shop.custom-frock.show', $order))
            ->assertOk()
            ->assertDontSee('Cancel this request');

        $this->actingAs($customer, 'web')
            ->post(route('shop.custom-frock.cancel', $order), ['reason' => 'Too late.'])
            ->assertSessionHas('error');

        $this->assertNotSame(CustomOrder::STATUS_CANCELLED, $order->fresh()->status);
    }

    public function test_an_admin_can_cancel_after_paying(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $customer = $this->customer();
        $order = $this->request($customer, CustomOrder::STATUS_PAID);
        $linked = $this->linkedOrder($order);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.custom-orders.cancel', $order), ['reason' => 'The customer called to cancel.'])
            ->assertSessionHas('success');

        $this->assertSame(CustomOrder::STATUS_CANCELLED, $order->fresh()->status);
        $this->assertSame('cancelled', $linked->fresh()->status);
    }

    // ── the two records stay in step ──────────────────────────────────────

    public function test_cancelling_the_linked_order_cancels_the_request(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $customer = $this->customer();
        $order = $this->request($customer, CustomOrder::STATUS_PAYMENT_PENDING);
        $linked = $this->linkedOrder($order);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.orders.cancel', $linked), ['reason' => 'The customer asked us to stop.'])
            ->assertSessionHas('success');

        $this->assertSame('cancelled', $linked->fresh()->status);
        $this->assertSame(
            CustomOrder::STATUS_CANCELLED,
            $order->fresh()->status,
            'the request must follow the order that pays for it'
        );
    }

    public function test_the_request_timeline_gains_the_cancelled_entry(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $customer = $this->customer();
        $order = $this->request($customer, CustomOrder::STATUS_PAYMENT_PENDING);
        $linked = $this->linkedOrder($order);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.orders.cancel', $linked), ['reason' => 'The customer asked us to stop.']);

        $history = $order->fresh()->statusHistory()->latest('id')->first();

        $this->assertSame(CustomOrder::STATUS_CANCELLED, $history->new_status);
        $this->assertSame('The customer asked us to stop.', $history->reason);
    }

    public function test_the_reason_reaches_the_cancelled_order(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $customer = $this->customer();
        $order = $this->request($customer, CustomOrder::STATUS_PAYMENT_PENDING);
        $linked = $this->linkedOrder($order);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.orders.cancel', $linked), ['reason' => 'The customer asked us to stop.']);

        $this->assertSame('The customer asked us to stop.', $linked->fresh()->cancellation_reason);
    }

    public function test_cancelling_the_request_cancels_the_order_that_pays_for_it(): void
    {
        Notification::fake();
        $customer = $this->customer();
        $order = $this->request($customer, CustomOrder::STATUS_QUOTED);
        $linked = $this->linkedOrder($order);

        $this->actingAs($customer, 'web')
            ->post(route('shop.custom-frock.cancel', $order), ['reason' => 'Changed our mind.']);

        $this->assertSame('cancelled', $linked->fresh()->status);
        $this->assertSame('Changed our mind.', $linked->fresh()->cancellation_reason);
    }

    public function test_a_cancelled_order_can_no_longer_be_paid(): void
    {
        Notification::fake();
        $customer = $this->customer();
        $order = $this->request($customer, CustomOrder::STATUS_QUOTED);
        $linked = $this->linkedOrder($order);

        $this->assertTrue($linked->fresh()->isPayNowEligible());

        $this->actingAs($customer, 'web')
            ->post(route('shop.custom-frock.cancel', $order), ['reason' => 'Changed our mind.']);

        $this->assertFalse($linked->fresh()->isPayNowEligible());
    }

    // ── the guard ──────────────────────────────────────────────────────────

    public function test_cancelling_the_order_of_a_shipped_frock_is_refused(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $customer = $this->customer();
        $order = $this->request($customer, CustomOrder::STATUS_SHIPPED);
        $linked = $this->linkedOrder($order);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.orders.cancel', $linked), ['reason' => 'Too late to cancel.'])
            ->assertSessionHas('error');

        $this->assertNotSame('cancelled', $linked->fresh()->status);
        $this->assertNotSame(CustomOrder::STATUS_CANCELLED, $order->fresh()->status);
    }

    public function test_an_admin_cannot_cancel_a_shipped_request(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $customer = $this->customer();
        $order = $this->request($customer, CustomOrder::STATUS_SHIPPED);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.custom-orders.cancel', $order), ['reason' => 'Too late.'])
            ->assertSessionHas('error');

        $this->assertNotSame(CustomOrder::STATUS_CANCELLED, $order->fresh()->status);
    }

    // ── it happens once ────────────────────────────────────────────────────

    public function test_cancelling_twice_does_not_do_it_twice(): void
    {
        Notification::fake();
        $customer = $this->customer();
        $order = $this->request($customer, CustomOrder::STATUS_QUOTED);
        $linked = $this->linkedOrder($order);

        $this->actingAs($customer, 'web')
            ->post(route('shop.custom-frock.cancel', $order), ['reason' => 'Changed our mind.']);

        $historyCount = $order->fresh()->statusHistory()->count();

        $this->actingAs($customer, 'web')
            ->post(route('shop.custom-frock.cancel', $order), ['reason' => 'Changed our mind again.']);

        $this->assertSame(
            $historyCount,
            $order->fresh()->statusHistory()->count(),
            'a second cancellation must not write a second history row'
        );
    }

    public function test_cancelling_twice_does_not_raise_a_second_refund_request(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $customer = $this->customer();
        $order = $this->request($customer, CustomOrder::STATUS_PAID);
        $linked = $this->linkedOrder($order);
        $linked->update(['amount_paid' => 27000, 'payment_status' => 'paid', 'status' => 'confirmed']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.custom-orders.cancel', $order), ['reason' => 'The customer called.']);

        $refunds = RefundRequest::where('order_id', $linked->id)->count();

        $this->assertSame(1, $refunds);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.custom-orders.cancel', $order), ['reason' => 'The customer called again.']);

        $this->assertSame(
            $refunds,
            RefundRequest::where('order_id', $linked->id)->count()
        );
    }

    // ── who may do it ──────────────────────────────────────────────────────

    public function test_a_customer_cannot_cancel_someone_elses_request(): void
    {
        Notification::fake();
        $owner = $this->customer();
        $stranger = $this->customer();
        $order = $this->request($owner, CustomOrder::STATUS_QUOTED);

        $this->actingAs($stranger, 'web')
            ->post(route('shop.custom-frock.cancel', $order), ['reason' => 'Not mine.'])
            ->assertForbidden();

        $this->assertNotSame(CustomOrder::STATUS_CANCELLED, $order->fresh()->status);
    }

    public function test_the_customer_is_told_when_the_admin_cancels(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $customer = $this->customer();
        $order = $this->request($customer, CustomOrder::STATUS_QUOTED);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.custom-orders.cancel', $order), ['reason' => 'The customer called to cancel.']);

        Notification::assertSentTo($customer, CustomOrderCancelled::class, function ($notification) {
            return $notification->reason === 'The customer called to cancel.';
        });
    }

    // ── the service refuses what it should ─────────────────────────────────

    public function test_the_service_refuses_a_customer_cancellation_after_payment(): void
    {
        $customer = $this->customer();
        $order = $this->request($customer, CustomOrder::STATUS_PAID);

        $this->expectException(\RuntimeException::class);

        app(CustomOrderService::class)->cancelAsCustomer($order, 'Too late.');
    }
}
