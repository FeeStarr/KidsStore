<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\CustomOrderQuote;
use App\Models\User;
use App\Notifications\CustomOrderInfoRequested;
use App\Notifications\CustomOrderMessageReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RequestInfoFlowTest extends TestCase
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

    private function orderFor(User $customer, string $status = CustomOrder::STATUS_NEEDS_INFORMATION): CustomOrder
    {
        return CustomOrder::create([
            'custom_order_number' => 'CO-INFO-' . uniqid(),
            'user_id' => $customer->id,
            'item_type' => 'frock',
            'status' => $status,
            'child_name' => 'Amara',
            'delivery_method' => 'pickup',
            'return_policy_acknowledged' => true,
        ]);
    }

    public function test_customer_show_renders_needs_information_action_card(): void
    {
        $order = $this->orderFor($this->customer());

        $this->actingAs($order->user, 'web')
            ->get(route('shop.custom-frock.show', $order))
            ->assertOk()
            ->assertSee('Action needed')
            ->assertSee("I've Provided the Requested Information", false)
            ->assertSee(route('shop.custom-frock.confirm-info', $order), false);
    }

    public function test_needs_information_card_hidden_for_other_statuses(): void
    {
        $order = $this->orderFor($this->customer(), CustomOrder::STATUS_UNDER_REVIEW);

        $this->actingAs($order->user, 'web')
            ->get(route('shop.custom-frock.show', $order))
            ->assertOk()
            ->assertDontSee('Action needed');
    }

    public function test_customer_can_confirm_information_provided(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $order = $this->orderFor($this->customer());

        $this->actingAs($order->user, 'web')
            ->from(route('shop.custom-frock.show', $order))
            ->post(route('shop.custom-frock.confirm-info', $order))
            ->assertRedirect(route('shop.custom-frock.show', $order))
            ->assertSessionHas('success');

        $this->assertSame(CustomOrder::STATUS_UNDER_REVIEW, $order->fresh()->status);

        Notification::assertSentTo($admin, CustomOrderMessageReceived::class);
    }

    public function test_confirm_info_shows_error_when_order_not_awaiting_information(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $order = $this->orderFor($this->customer(), CustomOrder::STATUS_QUOTED);

        $this->actingAs($order->user, 'web')
            ->from(route('shop.custom-frock.show', $order))
            ->post(route('shop.custom-frock.confirm-info', $order))
            ->assertRedirect(route('shop.custom-frock.show', $order))
            ->assertSessionHas('error');

        $this->assertSame(CustomOrder::STATUS_QUOTED, $order->fresh()->status);

        Notification::assertNotSentTo($admin, CustomOrderMessageReceived::class);
    }

    public function test_customer_cannot_confirm_info_on_another_users_order(): void
    {
        $order = $this->orderFor($this->customer());

        $this->actingAs($this->customer(), 'web')
            ->post(route('shop.custom-frock.confirm-info', $order))
            ->assertForbidden();
    }

    private function quotePayload(array $overrides = []): array
    {
        return array_merge([
            'base_price' => 12000,
            'fabric_cost' => 3500,
            'customization_cost' => 500,
            'embellishment_cost' => 2000,
            'measurement_fee' => 1000,
            'rush_fee' => 0,
            'delivery_fee' => 0,
            'discount' => 0,
            'total' => 19000,
            'valid_days' => 7,
            'notes' => null,
        ], $overrides);
    }

    public function test_admin_can_request_info_from_submitted_status(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $customer = $this->customer();
        $order = $this->orderFor($customer, CustomOrder::STATUS_SUBMITTED);

        $this->actingAs($admin, 'admin')
            ->from(route('admin.custom-orders.show', $order))
            ->post(route('admin.custom-orders.request-info', $order), [
                'message' => 'Please send the chest measurement.',
            ])
            ->assertRedirect(route('admin.custom-orders.show', $order))
            ->assertSessionHas('success');

        $this->assertSame(CustomOrder::STATUS_NEEDS_INFORMATION, $order->fresh()->status);

        $this->assertDatabaseHas('custom_order_messages', [
            'custom_order_id' => $order->id,
            'sender_type' => 'admin',
            'sender_id' => $admin->id,
            'message' => 'Please send the chest measurement.',
            'is_customer_visible' => true,
        ]);

        Notification::assertSentTo(
            $customer,
            CustomOrderInfoRequested::class,
            fn (CustomOrderInfoRequested $n) => $n->message === 'Please send the chest measurement.'
        );

        $this->assertDatabaseHas('custom_order_status_history', [
            'custom_order_id' => $order->id,
            'old_status' => CustomOrder::STATUS_SUBMITTED,
            'new_status' => CustomOrder::STATUS_NEEDS_INFORMATION,
            'changed_by' => $admin->id,
        ]);
    }

    public function test_admin_can_request_info_from_under_review_status(): void
    {
        Notification::fake();
        $order = $this->orderFor($this->customer(), CustomOrder::STATUS_UNDER_REVIEW);

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.custom-orders.request-info', $order), [
                'message' => 'Which colour do you prefer?',
            ])
            ->assertSessionHas('success');

        $this->assertSame(CustomOrder::STATUS_NEEDS_INFORMATION, $order->fresh()->status);
    }

    public function test_request_info_on_illegal_status_shows_error_not_500(): void
    {
        Notification::fake();
        $order = $this->orderFor($this->customer(), CustomOrder::STATUS_IN_PRODUCTION);

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.custom-orders.request-info', $order), [
                'message' => 'Too late for this.',
            ])
            ->assertSessionHas('error');

        $this->assertSame(CustomOrder::STATUS_IN_PRODUCTION, $order->fresh()->status);
        $this->assertDatabaseCount('custom_order_messages', 0);
    }

    public function test_admin_page_shows_request_info_for_submitted_order(): void
    {
        $order = $this->orderFor($this->customer(), CustomOrder::STATUS_SUBMITTED);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.custom-orders.show', $order))
            ->assertOk()
            ->assertSee('Request Info')
            ->assertSee('Create Quote');
    }

    public function test_admin_can_quote_directly_from_submitted_status(): void
    {
        Notification::fake();
        $order = $this->orderFor($this->customer(), CustomOrder::STATUS_SUBMITTED);

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.custom-orders.quote', $order), $this->quotePayload())
            ->assertSessionHas('success');

        $this->assertSame(CustomOrder::STATUS_QUOTED, $order->fresh()->status);
        $this->assertSame(1, (int) $order->quotes()->max('version'));
    }

    public function test_admin_can_revise_quote_from_needs_revision_status(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $order = $this->orderFor($this->customer(), CustomOrder::STATUS_NEEDS_REVISION);

        CustomOrderQuote::create([
            'custom_order_id' => $order->id,
            'version' => 1,
            'base_price' => 12000,
            'total' => 19000,
            'status' => CustomOrderQuote::STATUS_APPROVED,
            'valid_until' => now()->addDays(7),
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.custom-orders.quote', $order), $this->quotePayload(['total' => 17500]))
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(CustomOrder::STATUS_QUOTED, $order->status);
        $this->assertSame(2, (int) $order->quotes()->max('version'));
    }

    public function test_store_quote_guard_returns_error_for_illegal_status(): void
    {
        Notification::fake();
        $order = $this->orderFor($this->customer(), CustomOrder::STATUS_IN_PRODUCTION);

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.custom-orders.quote', $order), $this->quotePayload())
            ->assertSessionHas('error');

        $this->assertSame(CustomOrder::STATUS_IN_PRODUCTION, $order->fresh()->status);
        $this->assertDatabaseCount('custom_order_quotes', 0);
    }

    public function test_admin_can_continue_review_from_needs_information(): void
    {
        Notification::fake();
        $customer = $this->customer();
        $order = $this->orderFor($customer);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.custom-orders.show', $order))
            ->assertOk()
            ->assertSee('Info Received &mdash; Continue Review', false);

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.custom-orders.review', $order))
            ->assertSessionHas('success');

        $this->assertSame(CustomOrder::STATUS_UNDER_REVIEW, $order->fresh()->status);
    }
}
