<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\User;
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
