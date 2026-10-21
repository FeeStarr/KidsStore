<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\Order;
use App\Models\User;
use App\Services\CustomPaymentService;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A custom frock record is called a "Custom Request" until the customer commits - the
 * moment a quote is approved and a payable ORD-... is created - and a "Custom Order"
 * from then on. The label reflects commitment, not payment: an unpaid order is still
 * an order.
 */
class CustomOrderLifecycleLabelTest extends TestCase
{
    private function customer(): User
    {
        return User::factory()->create(['role' => User::ROLE_CUSTOMER, 'is_active' => true]);
    }

    private function order(User $customer, string $status): CustomOrder
    {
        return CustomOrder::create([
            'custom_order_number' => 'CO-LBL-'.uniqid(),
            'user_id' => $customer->id,
            'item_type' => 'frock',
            'status' => $status,
            'child_name' => 'Amara',
            'total_amount' => 15000,
            'delivery_method' => 'delivery',
            'delivery_address' => '12 Test Street',
            'return_policy_acknowledged' => true,
        ]);
    }

    public static function preApprovalStatuses(): array
    {
        return array_map(fn ($s) => [$s], [
            CustomOrder::STATUS_DRAFT,
            CustomOrder::STATUS_SUBMITTED,
            CustomOrder::STATUS_UNDER_REVIEW,
            CustomOrder::STATUS_NEEDS_INFORMATION,
            CustomOrder::STATUS_QUOTE_PENDING,
            CustomOrder::STATUS_QUOTED,
            CustomOrder::STATUS_NEEDS_REVISION,
        ]);
    }

    public static function postApprovalStatuses(): array
    {
        return array_map(fn ($s) => [$s], [
            CustomOrder::STATUS_CUSTOMER_APPROVED,
            CustomOrder::STATUS_PAYMENT_PENDING,
            CustomOrder::STATUS_PAID,
            CustomOrder::STATUS_IN_PRODUCTION,
            CustomOrder::STATUS_READY_FOR_DELIVERY,
            CustomOrder::STATUS_SHIPPED,
            CustomOrder::STATUS_COMPLETED,
        ]);
    }

    #[DataProvider('preApprovalStatuses')]
    public function test_record_is_a_custom_request_before_approval(string $status): void
    {
        $order = $this->order($this->customer(), $status);

        $this->assertFalse($order->hasPayableOrder());
        $this->assertSame('Custom Request', $order->lifecycle_label);
    }

    #[DataProvider('postApprovalStatuses')]
    public function test_record_is_a_custom_order_once_a_payable_order_exists(string $status): void
    {
        $customer = $this->customer();
        $order = $this->order($customer, $status);

        app(CustomPaymentService::class)->createLinkedOrder($order);

        $this->assertTrue($order->fresh()->hasPayableOrder());
        $this->assertSame('Custom Order', $order->fresh()->lifecycle_label);
    }

    /**
     * Cancelled and rejected can occur on either side of approval. The label follows
     * what actually exists rather than the status.
     */
    public function test_cancelling_after_approval_still_reads_as_a_custom_order(): void
    {
        $customer = $this->customer();
        $order = $this->order($customer, CustomOrder::STATUS_PAYMENT_PENDING);

        $linked = app(CustomPaymentService::class)->createLinkedOrder($order);
        $order->update(['status' => CustomOrder::STATUS_CANCELLED]);

        $fresh = $order->fresh();
        $this->assertSame(CustomOrder::STATUS_CANCELLED, $fresh->status);
        $this->assertSame('Custom Order', $fresh->lifecycle_label);
        $this->assertFalse($fresh->isStrandedAfterApproval());
    }

    /**
     * A record left in a post-approval status by the old non-atomic flow must not
     * claim an order exists when none can be found.
     */
    public function test_stranded_record_does_not_claim_an_order_exists(): void
    {
        $order = $this->order($this->customer(), CustomOrder::STATUS_CUSTOMER_APPROVED);

        $this->assertFalse($order->hasPayableOrder());
        $this->assertTrue($order->isStrandedAfterApproval());
        $this->assertSame(
            'Custom Request',
            $order->lifecycle_label,
            'A record with no payable order must not be presented as a Custom Order.'
        );
    }

    public function test_customer_index_lists_requests_without_the_linked_order_reference(): void
    {
        Notification::fake();
        $customer = $this->customer();

        $request = $this->order($customer, CustomOrder::STATUS_QUOTED);
        $approved = $this->order($customer, CustomOrder::STATUS_PAYMENT_PENDING);
        $linked = app(CustomPaymentService::class)->createLinkedOrder($approved);

        $response = $this->actingAs($customer, 'web')->get(route('shop.custom-frock.index'));
        $response->assertOk();
        $response->assertSee('Custom Request '.$request->custom_order_number);
        $response->assertSee('Custom Order '.$approved->custom_order_number);

        // The linked order is a billing record. Everything the customer needs is
        // on the request itself, so the order reference is not put in front of them.
        $response->assertDontSee($linked->reference);
    }

    /**
     * The request page carries the whole story - the quote, the sizing, the delivery
     * and the payment - so the linked order's reference is not put in front of the
     * customer there either.
     */
    public function test_the_request_page_does_not_show_the_linked_order_reference(): void
    {
        Notification::fake();
        $customer = $this->customer();

        $order = $this->order($customer, CustomOrder::STATUS_PAYMENT_PENDING);
        $linked = app(CustomPaymentService::class)->createLinkedOrder($order);

        $this->actingAs($customer, 'web')
            ->get(route('shop.custom-frock.show', $order))
            ->assertOk()
            ->assertDontSee($linked->reference);
    }

    /**
     * A custom frock keeps its own list. It is not a shop order, and showing its
     * payment record in My Orders as well meant the same purchase appeared twice
     * with two different statuses.
     */
    public function test_a_custom_frock_does_not_appear_in_my_orders(): void
    {
        $customer = $this->customer();
        $order = $this->order($customer, CustomOrder::STATUS_PAYMENT_PENDING);
        $linked = app(CustomPaymentService::class)->createLinkedOrder($order);

        $response = $this->actingAs($customer, 'web')->get(route('shop.account.orders.index'));
        $response->assertOk();
        $response->assertDontSee($linked->reference);
        $response->assertDontSee('Custom frock '.$order->custom_order_number);
    }

    public function test_a_custom_frock_still_appears_in_my_custom_frocks(): void
    {
        $customer = $this->customer();
        $order = $this->order($customer, CustomOrder::STATUS_PAYMENT_PENDING);
        app(CustomPaymentService::class)->createLinkedOrder($order);

        $this->actingAs($customer, 'web')
            ->get(route('shop.custom-frock.index'))
            ->assertOk()
            ->assertSee($order->custom_order_number);
    }

    public function test_admin_order_note_links_back_to_the_custom_request(): void
    {
        $customer = $this->customer();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
        $order = $this->order($customer, CustomOrder::STATUS_PAYMENT_PENDING);
        $linked = app(CustomPaymentService::class)->createLinkedOrder($order);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.orders.show', $linked));
        $response->assertOk();
        $response->assertSee('Custom Order '.$order->custom_order_number);
    }

    public function test_linked_order_is_unique_per_custom_request(): void
    {
        $customer = $this->customer();
        $order = $this->order($customer, CustomOrder::STATUS_PAYMENT_PENDING);

        app(CustomPaymentService::class)->createLinkedOrder($order);

        $this->assertSame(1, Order::where('custom_order_id', $order->id)->count());
    }
}
