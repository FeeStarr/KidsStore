<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\Order;
use App\Models\User;
use App\Notifications\CustomOrderCompleted;
use App\Notifications\CustomOrderInProduction;
use App\Notifications\CustomOrderPaid;
use App\Notifications\CustomOrderReady;
use App\Notifications\CustomOrderRejected;
use App\Notifications\CustomOrderShipped;
use App\Notifications\CustomQuoteExpired;
use App\Services\CustomOrderService;
use App\Services\CustomQuoteService;
use Illuminate\Contracts\Mail\Factory;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Every stage of a custom order that the customer can observe must produce an
 * email. This suite walks the whole lifecycle and asserts the right notification
 * fires at each step - the gaps it guards (paid, shipped, ready, rejected,
 * quote-expired) were all silent before.
 */
class CustomOrderEmailCoverageTest extends TestCase
{
    private function customer(): User
    {
        return User::factory()->create(['role' => User::ROLE_CUSTOMER, 'is_active' => true]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
    }

    private function order(User $customer, string $status, array $overrides = []): CustomOrder
    {
        return CustomOrder::create(array_merge([
            'custom_order_number' => 'CO-EMAIL-'.uniqid(),
            'user_id' => $customer->id,
            'item_type' => 'frock',
            'status' => $status,
            'child_name' => 'Amara',
            'total_amount' => 0,
            'delivery_method' => 'delivery',
            'delivery_address' => '12 Test Street',
            'return_policy_acknowledged' => true,
        ], $overrides));
    }

    public function test_reject_notifies_customer_with_the_reason(): void
    {
        Notification::fake();
        $customer = $this->customer();
        $order = $this->order($customer, CustomOrder::STATUS_SUBMITTED);

        app(CustomOrderService::class)
            ->reject($order, 'We cannot source this fabric right now', $this->admin()->id);

        $this->assertSame(CustomOrder::STATUS_REJECTED, $order->fresh()->status);

        Notification::assertSentTo($customer, CustomOrderRejected::class, function (CustomOrderRejected $n) use ($customer): bool {
            $lines = implode(' ', $n->toMail($customer)->introLines);
            $this->assertStringContainsString('cannot source this fabric', $lines);

            return true;
        });
    }

    public function test_quote_expiry_marks_order_and_notifies_customer(): void
    {
        Notification::fake();
        $customer = $this->customer();
        $order = $this->order($customer, CustomOrder::STATUS_QUOTED, [
            'quote_valid_until' => now()->subDay(),
        ]);

        $expired = app(CustomQuoteService::class)->checkExpiry();

        $this->assertSame(1, $expired);
        $this->assertSame(CustomOrder::STATUS_QUOTE_EXPIRED, $order->fresh()->status);
        Notification::assertSentTo($customer, CustomQuoteExpired::class);
    }

    public function test_payment_confirmation_notifies_customer(): void
    {
        Notification::fake();
        $customer = $this->customer();
        $order = $this->order($customer, CustomOrder::STATUS_PAYMENT_PENDING);

        $linked = Order::create([
            'reference' => 'ORD-COVERAGE-'.uniqid(),
            'order_date' => now(),
            'custom_order_id' => $order->id,
            'customer_id' => $customer->id,
            'payment_status' => 'unpaid',
            'grand_total' => 5000,
            'amount_paid' => 0,
        ]);

        $linked->update(['payment_status' => 'paid', 'amount_paid' => 5000]);

        $this->assertSame(CustomOrder::STATUS_PAID, $order->fresh()->status);
        Notification::assertSentTo($customer, CustomOrderPaid::class);
    }

    public function test_payment_mail_failure_does_not_break_payment_confirmation(): void
    {
        $customer = $this->customer();
        $order = $this->order($customer, CustomOrder::STATUS_PAYMENT_PENDING);

        $this->swapFailingMailer();

        $linked = Order::create([
            'reference' => 'ORD-FAILPAY-'.uniqid(),
            'order_date' => now(),
            'custom_order_id' => $order->id,
            'customer_id' => $customer->id,
            'payment_status' => 'unpaid',
            'grand_total' => 5000,
            'amount_paid' => 0,
        ]);

        $linked->update(['payment_status' => 'paid', 'amount_paid' => 5000]);

        $this->assertSame(CustomOrder::STATUS_PAID, $order->fresh()->status);
        $this->assertSame(5000.0, (float) $order->fresh()->amount_paid);
    }

    public function test_production_start_notifies_customer(): void
    {
        Notification::fake();
        $customer = $this->customer();
        $order = $this->order($customer, CustomOrder::STATUS_PAID);

        app(CustomOrderService::class)->startProduction($order, $this->admin()->id);

        $this->assertSame(CustomOrder::STATUS_IN_PRODUCTION, $order->fresh()->status);
        Notification::assertSentTo($customer, CustomOrderInProduction::class);
    }

    public function test_ready_for_delivery_notifies_customer(): void
    {
        Notification::fake();
        $customer = $this->customer();
        $order = $this->order($customer, CustomOrder::STATUS_QUALITY_CHECK);

        app(CustomOrderService::class)->markReadyForDelivery($order, $this->admin()->id);

        $this->assertSame(CustomOrder::STATUS_READY_FOR_DELIVERY, $order->fresh()->status);
        Notification::assertSentTo($customer, CustomOrderReady::class);
    }

    public function test_ready_for_pickup_notifies_customer(): void
    {
        Notification::fake();
        $customer = $this->customer();
        $order = $this->order($customer, CustomOrder::STATUS_QUALITY_CHECK, [
            'delivery_method' => 'pickup',
        ]);

        app(CustomOrderService::class)->markReadyForPickup($order, $this->admin()->id);

        $this->assertSame(CustomOrder::STATUS_READY_FOR_PICKUP, $order->fresh()->status);
        Notification::assertSentTo($customer, CustomOrderReady::class);
    }

    public function test_shipped_notifies_customer_with_tracking_details(): void
    {
        Notification::fake();
        $customer = $this->customer();
        $order = $this->order($customer, CustomOrder::STATUS_READY_FOR_DELIVERY, [
            'tracking_number' => 'DHL-12345',
            'courier_name' => 'DHL',
        ]);

        app(CustomOrderService::class)->markShipped($order, $this->admin()->id);

        $this->assertSame(CustomOrder::STATUS_SHIPPED, $order->fresh()->status);

        Notification::assertSentTo($customer, CustomOrderShipped::class, function (CustomOrderShipped $n) use ($customer): bool {
            $lines = implode(' ', $n->toMail($customer)->introLines);
            $this->assertStringContainsString('DHL-12345', $lines);

            return true;
        });
    }

    public function test_completed_notifies_customer(): void
    {
        Notification::fake();
        $customer = $this->customer();
        $order = $this->order($customer, CustomOrder::STATUS_SHIPPED);

        app(CustomOrderService::class)->complete($order, $this->admin()->id);

        $this->assertSame(CustomOrder::STATUS_COMPLETED, $order->fresh()->status);
        Notification::assertSentTo($customer, CustomOrderCompleted::class);
    }

    public function test_ship_mail_failure_does_not_break_the_ship_action(): void
    {
        $customer = $this->customer();
        $order = $this->order($customer, CustomOrder::STATUS_READY_FOR_DELIVERY);

        $this->swapFailingMailer();

        app(CustomOrderService::class)->markShipped($order, $this->admin()->id);

        $this->assertSame(CustomOrder::STATUS_SHIPPED, $order->fresh()->status);
    }

    private function swapFailingMailer(): void
    {
        app()->instance('mail.manager', new class implements Factory
        {
            public function mailer($name = null)
            {
                return new class
                {
                    public function send(...$args)
                    {
                        throw new \RuntimeException('SMTP connection refused');
                    }
                };
            }
        });
    }
}
