<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\CustomOrderQuote;
use App\Models\Order;
use App\Models\User;
use App\Notifications\CustomQuoteApproved;
use App\Services\CustomPaymentService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Approving a quote is the point where a customer commits money, so it has to be
 * all-or-nothing: either the quote is approved, the lifecycle advances and exactly
 * one payable order exists, or nothing happened at all.
 *
 * These cover the guarantees in CustomQuoteService::approveAndCreateOrder() and the
 * unique index on orders.custom_order_id that backs them.
 */
class CustomQuoteApprovalAtomicityTest extends TestCase
{
    private function customer(): User
    {
        return User::factory()->create(['role' => User::ROLE_CUSTOMER, 'is_active' => true]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
    }

    private function quotedOrder(User $customer): CustomOrder
    {
        return CustomOrder::create([
            'custom_order_number' => 'CO-ATOM-'.uniqid(),
            'user_id' => $customer->id,
            'item_type' => 'frock',
            'status' => CustomOrder::STATUS_QUOTED,
            'child_name' => 'Amara',
            'total_amount' => 15000,
            'delivery_method' => 'delivery',
            'delivery_address' => '12 Test Street',
            'return_policy_acknowledged' => true,
        ]);
    }

    private function draftQuote(CustomOrder $order, User $admin): CustomOrderQuote
    {
        return CustomOrderQuote::create([
            'custom_order_id' => $order->id,
            'version' => 1,
            'base_price' => 15000,
            'total' => 15000,
            'valid_until' => now()->addDays(7),
            'status' => CustomOrderQuote::STATUS_DRAFT,
            'created_by' => $admin->id,
        ]);
    }

    private function breakPaymentService(): void
    {
        $this->mock(CustomPaymentService::class, function ($mock): void {
            $mock->shouldReceive('createLinkedOrder')
                ->andThrow(new \RuntimeException('simulated order creation failure'));
        });
    }

    public function test_approval_records_the_quote_as_approved(): void
    {
        Notification::fake();
        $customer = $this->customer();
        $order = $this->quotedOrder($customer);
        $quote = $this->draftQuote($order, $this->admin());

        $this->actingAs($customer, 'web')
            ->from(route('shop.custom-frock.show', $order))
            ->post(route('shop.custom-frock.approve-quote', $order))
            ->assertRedirect();

        // The quote must be genuinely marked approved, so approvedQuote() resolves it
        // instead of silently falling back to the latest draft.
        $approved = $order->fresh()->approvedQuote();
        $this->assertNotNull($approved, 'approvedQuote() must not fall back to latestQuote().');
        $this->assertSame(CustomOrderQuote::STATUS_APPROVED, $approved->status);
        $this->assertSame($quote->id, $approved->id);
        $this->assertNotNull($approved->approved_at);
    }

    public function test_approval_creates_exactly_one_payable_order_and_reaches_payment_pending(): void
    {
        Notification::fake();
        $customer = $this->customer();
        $admin = $this->admin();
        $order = $this->quotedOrder($customer);
        $this->draftQuote($order, $admin);

        $this->actingAs($customer, 'web')
            ->from(route('shop.custom-frock.show', $order))
            ->post(route('shop.custom-frock.approve-quote', $order))
            ->assertRedirect();

        $this->assertSame(CustomOrder::STATUS_PAYMENT_PENDING, $order->fresh()->status);
        $this->assertSame(1, Order::where('custom_order_id', $order->id)->count());

        $linked = $order->fresh()->order;
        $this->assertNotNull($linked);
        $this->assertSame(15000.0, (float) $linked->grand_total);

        // Dispatched after the commit, so the admins hear about a real order.
        Notification::assertSentTo($admin, CustomQuoteApproved::class);
    }

    /**
     * If linked-order creation fails, the quote approval and lifecycle change must
     * roll back with it - the customer must not be left with an approved quote and
     * an order that does not exist, which is the exact state the stage-dependent
     * "Custom Order" label would wrongly claim.
     */
    public function test_failed_order_creation_rolls_back_the_entire_approval(): void
    {
        Notification::fake();
        $customer = $this->customer();
        $order = $this->quotedOrder($customer);
        $quote = $this->draftQuote($order, $this->admin());

        $this->breakPaymentService();

        $response = $this->actingAs($customer, 'web')
            ->from(route('shop.custom-frock.show', $order))
            ->post(route('shop.custom-frock.approve-quote', $order));

        $response->assertRedirect(route('shop.custom-frock.show', $order));
        $response->assertSessionHas('error');

        // Nothing may be committed.
        $this->assertSame(CustomOrder::STATUS_QUOTED, $order->fresh()->status);
        $this->assertSame(CustomOrderQuote::STATUS_DRAFT, $quote->fresh()->status);
        $this->assertNull($quote->fresh()->approved_at);
        $this->assertDatabaseMissing('orders', ['custom_order_id' => $order->id]);
        $this->assertNull($order->fresh()->order);
    }

    /**
     * Notifications are dispatched after the transaction commits, so a rolled-back
     * approval must not have told the admins anything.
     */
    public function test_no_notification_is_sent_when_the_approval_rolls_back(): void
    {
        Notification::fake();
        $customer = $this->customer();
        $order = $this->quotedOrder($customer);
        $this->draftQuote($order, $this->admin());

        $this->breakPaymentService();

        $this->actingAs($customer, 'web')
            ->from(route('shop.custom-frock.show', $order))
            ->post(route('shop.custom-frock.approve-quote', $order));

        Notification::assertNothingSent();
    }

    public function test_second_approval_does_not_create_a_second_payable_order(): void
    {
        Notification::fake();
        $customer = $this->customer();
        $order = $this->quotedOrder($customer);
        $this->draftQuote($order, $this->admin());

        $this->actingAs($customer, 'web')
            ->from(route('shop.custom-frock.show', $order))
            ->post(route('shop.custom-frock.approve-quote', $order))
            ->assertRedirect();

        $firstLinked = $order->fresh()->order;
        $this->assertNotNull($firstLinked);

        // Re-submitting (double click, browser retry) must be refused.
        $response = $this->actingAs($customer, 'web')
            ->from(route('shop.custom-frock.show', $order->fresh()))
            ->post(route('shop.custom-frock.approve-quote', $order->fresh()));

        $response->assertSessionHas('error');

        $this->assertSame(1, Order::where('custom_order_id', $order->id)->count());
        $this->assertTrue($order->fresh()->order->is($firstLinked), 'The original order must be untouched.');
    }

    /**
     * The database-level backstop. Even if the row lock and the service guard were
     * both bypassed, the unique index refuses a second linked order.
     */
    public function test_database_rejects_a_second_linked_order_for_the_same_custom_order(): void
    {
        $customer = $this->customer();
        $order = $this->quotedOrder($customer);
        $this->draftQuote($order, $this->admin());

        $this->actingAs($customer, 'web')
            ->from(route('shop.custom-frock.show', $order))
            ->post(route('shop.custom-frock.approve-quote', $order));

        $this->assertSame(1, Order::where('custom_order_id', $order->id)->count());

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('orders')->insert([
            'reference' => 'ORD-DUPE-'.uniqid(),
            'customer_id' => $customer->id,
            'custom_order_id' => $order->id,
            'order_date' => now(),
            'status' => 'pending payment',
            'subtotal' => 15000,
            'grand_total' => 15000,
            'total_amount' => 15000,
        ]);
    }
}
