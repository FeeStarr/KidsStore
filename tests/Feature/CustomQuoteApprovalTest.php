<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\CustomOrderQuote;
use App\Models\DeliveryLocation;
use App\Models\User;
use App\Notifications\CustomOrderCancelled;
use App\Notifications\CustomOrderMessageReceived;
use App\Notifications\CustomOrderReceived;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CustomQuoteApprovalTest extends TestCase
{
    private function customer(): User
    {
        return User::factory()->create(['role' => User::ROLE_CUSTOMER, 'is_active' => true]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
    }

    private function quotedOrder(User $customer, array $overrides = []): CustomOrder
    {
        return CustomOrder::create(array_merge([
            'custom_order_number' => 'CO-QUOTE-' . uniqid(),
            'user_id' => $customer->id,
            'item_type' => 'frock',
            'status' => CustomOrder::STATUS_QUOTED,
            'child_name' => 'Amara',
            'base_product_id' => null,
            'total_amount' => 5000,
            'delivery_method' => 'delivery',
            'delivery_address' => '12 Test Street',
            'return_policy_acknowledged' => true,
        ], $overrides));
    }

    private function addQuote(CustomOrder $order, User $admin): CustomOrderQuote
    {
        return CustomOrderQuote::create([
            'custom_order_id' => $order->id,
            'version' => 1,
            'base_price' => 5000,
            'total' => 5000,
            'valid_until' => now()->addDays(7),
            'status' => CustomOrderQuote::STATUS_DRAFT,
            'created_by' => $admin->id,
        ]);
    }

    public function test_approve_quote_creates_linked_order_with_custom_item_and_renders(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $order = $this->quotedOrder($customer);
        $this->addQuote($order, $admin);

        $this->actingAs($customer, 'web')
            ->post(route('shop.custom-frock.approve-quote', $order))
            ->assertRedirect(route('shop.account.orders.show', $order->order))
            ->assertSessionHas('success');

        $this->assertSame(CustomOrder::STATUS_CUSTOMER_APPROVED, $order->fresh()->status);

        $linked = $order->order;
        $this->assertNotNull($linked);
        $this->assertMatchesRegularExpression('/^ORD-\d{8}-[A-Z0-9]{6}$/', $linked->reference);

        $item = $linked->items()->first();
        $this->assertNotNull($item);
        $this->assertNull($item->product_id);

        $this->actingAs($customer, 'web')
            ->get(route('shop.account.orders.show', $linked))
            ->assertOk()
            ->assertSee('Custom Frock');
    }

    public function test_request_changes_notifies_admins_and_records_message(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $customer = $this->customer();
        $order = $this->quotedOrder($customer);

        $this->actingAs($customer, 'web')
            ->from(route('shop.custom-frock.show', $order))
            ->post(route('shop.custom-frock.request-changes', $order), [
                'message' => 'Please make the sleeves wider',
            ])
            ->assertRedirect(route('shop.custom-frock.show', $order))
            ->assertSessionHas('success');

        $this->assertSame(CustomOrder::STATUS_NEEDS_REVISION, $order->fresh()->status);
        $this->assertDatabaseHas('custom_order_messages', [
            'custom_order_id' => $order->id,
            'sender_type' => 'customer',
            'sender_id' => $customer->id,
            'message' => 'Please make the sleeves wider',
        ]);

        Notification::assertSentTo($admin, CustomOrderMessageReceived::class);
    }

    public function test_customer_cancel_quoted_order_notifies_admins(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $customer = $this->customer();
        $order = $this->quotedOrder($customer);

        $this->actingAs($customer, 'web')
            ->from(route('shop.custom-frock.show', $order))
            ->post(route('shop.custom-frock.cancel', $order))
            ->assertRedirect(route('shop.custom-frock.show', $order))
            ->assertSessionHas('success');

        $this->assertSame(CustomOrder::STATUS_CANCELLED, $order->fresh()->status);

        Notification::assertSentTo($admin, CustomOrderCancelled::class);
        Notification::assertSentTo($customer, CustomOrderCancelled::class);
    }

    public function test_custom_order_submit_notifies_admins(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $customer = $this->customer();

        $location = DeliveryLocation::firstOrCreate(
            ['name' => 'Test City'],
            ['state' => 'Lagos', 'is_active' => true]
        );

        $this->actingAs($customer, 'web')
            ->post(route('shop.custom-frock.store'), [
                'child_name' => 'Test Child',
                'delivery_method' => 'delivery',
                'delivery_location_id' => $location->id,
                'delivery_address' => '123 Test Street',
                'primary_colour' => 'Blue',
                'standard_size' => '2-3 Years',
                'return_policy_acknowledged' => '1',
            ])
            ->assertRedirect();

        Notification::assertSentTo($customer, CustomOrderReceived::class);
        Notification::assertSentTo($admin, CustomOrderReceived::class);
    }
}
