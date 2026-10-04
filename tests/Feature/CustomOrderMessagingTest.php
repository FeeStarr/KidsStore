<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\User;
use App\Notifications\CustomOrderMessageReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomOrderMessagingTest extends TestCase
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

    private function orderFor(User $customer): CustomOrder
    {
        return CustomOrder::create([
            'custom_order_number' => 'CO-MSG-' . uniqid(),
            'user_id' => $customer->id,
            'item_type' => 'frock',
            'status' => CustomOrder::STATUS_SUBMITTED,
            'child_name' => 'Amara',
            'delivery_method' => 'pickup',
            'return_policy_acknowledged' => true,
        ]);
    }

    public function test_show_page_renders_reply_form(): void
    {
        $order = $this->orderFor($this->customer());

        $this->actingAs($order->user, 'web')
            ->get(route('shop.custom-frock.show', $order))
            ->assertOk()
            ->assertSee('name="message"', false)
            ->assertSee('name="attachment"', false)
            ->assertSee('multipart/form-data', false);
    }

    public function test_customer_can_reply_to_kidsflairr_message(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $customer = $this->customer();
        $order = $this->orderFor($customer);

        $order->messages()->create([
            'sender_type' => 'admin',
            'sender_id' => $admin->id,
            'message' => 'Would you like the sleeves longer?',
            'is_customer_visible' => true,
            'created_at' => now(),
        ]);

        $this->actingAs($customer, 'web')
            ->from(route('shop.custom-frock.show', $order))
            ->post(route('shop.custom-frock.messages', $order), [
                'message' => 'Yes please, 2cm longer.',
            ])
            ->assertRedirect(route('shop.custom-frock.show', $order))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('custom_order_messages', [
            'custom_order_id' => $order->id,
            'sender_type' => 'customer',
            'sender_id' => $customer->id,
            'message' => 'Yes please, 2cm longer.',
        ]);

        Notification::assertSentTo($admin, CustomOrderMessageReceived::class);
    }

    public function test_customer_can_start_conversation_without_prior_messages(): void
    {
        $customer = $this->customer();
        $order = $this->orderFor($customer);

        $this->actingAs($customer, 'web')
            ->post(route('shop.custom-frock.messages', $order), [
                'message' => 'Is the ankara fabric in stock?',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseCount('custom_order_messages', 1);
    }

    public function test_customer_reply_requires_message_or_attachment(): void
    {
        $customer = $this->customer();
        $order = $this->orderFor($customer);

        $this->actingAs($customer, 'web')
            ->from(route('shop.custom-frock.show', $order))
            ->post(route('shop.custom-frock.messages', $order), [])
            ->assertRedirect(route('shop.custom-frock.show', $order))
            ->assertSessionHasErrors('message');

        $this->assertDatabaseCount('custom_order_messages', 0);
    }

    public function test_customer_reply_accepts_attachment(): void
    {
        Storage::fake('custom_orders');
        Notification::fake();
        $admin = $this->admin();
        $customer = $this->customer();
        $order = $this->orderFor($customer);

        $this->actingAs($customer, 'web')
            ->post(route('shop.custom-frock.messages', $order), [
                'message' => 'Here is the photo you asked for.',
                'attachment' => UploadedFile::fake()->image('design.png'),
            ])
            ->assertSessionHas('success');

        $message = $order->messages()->where('sender_type', 'customer')->firstOrFail();
        $this->assertNotNull($message->custom_order_file_id);

        $file = $message->file;
        $this->assertSame('message_attachment', $file->file_type);
        $this->assertSame($order->id, $file->custom_order_id);

        $this->actingAs($customer, 'web')
            ->get(route('shop.custom-frock.show', $order))
            ->assertOk()
            ->assertSee(route('shop.custom-frock.file', [$order, $file]), false);

        Notification::assertSentTo($admin, CustomOrderMessageReceived::class);
    }

    public function test_admin_can_reply_with_attachment(): void
    {
        Storage::fake('custom_orders');
        Notification::fake();
        $admin = $this->admin();
        $customer = $this->customer();
        $order = $this->orderFor($customer);

        $order->messages()->create([
            'sender_type' => 'customer',
            'sender_id' => $customer->id,
            'message' => 'Can you add a bow?',
            'is_customer_visible' => true,
            'created_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.custom-orders.message', $order), [
                'message' => 'Yes, bow added to the design.',
                'attachment' => UploadedFile::fake()->image('bow.png'),
            ])
            ->assertSessionHas('success');

        $message = $order->messages()->where('sender_type', 'admin')->firstOrFail();
        $this->assertSame('Yes, bow added to the design.', $message->message);
        $this->assertNotNull($message->custom_order_file_id);
        $this->assertSame('message_attachment', $message->file->file_type);

        Notification::assertSentTo($customer, CustomOrderMessageReceived::class);
    }

    public function test_customer_cannot_message_another_users_order(): void
    {
        $owner = $this->customer();
        $stranger = $this->customer();
        $order = $this->orderFor($owner);

        $this->actingAs($stranger, 'web')
            ->post(route('shop.custom-frock.messages', $order), ['message' => 'hi'])
            ->assertForbidden();

        $this->assertDatabaseCount('custom_order_messages', 0);
    }
}
