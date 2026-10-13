<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\User;
use App\Notifications\CustomQuoteReady;
use App\Services\CustomQuoteService;
use Illuminate\Contracts\Mail\Factory;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CustomQuoteReadyNotificationTest extends TestCase
{
    private function customer(): User
    {
        return User::factory()->create(['role' => User::ROLE_CUSTOMER, 'is_active' => true]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
    }

    private function submittedOrder(User $customer): CustomOrder
    {
        return CustomOrder::create([
            'custom_order_number' => 'CO-QRE-'.uniqid(),
            'user_id' => $customer->id,
            'item_type' => 'frock',
            'status' => CustomOrder::STATUS_SUBMITTED,
            'child_name' => 'Amara',
            'total_amount' => 0,
            'delivery_method' => 'delivery',
            'delivery_address' => '12 Test Street',
            'return_policy_acknowledged' => true,
        ]);
    }

    public function test_quote_email_states_the_real_expiry_not_a_fixed_window(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $customer = $this->customer();
        $order = $this->submittedOrder($customer);

        $quote = app(CustomQuoteService::class)
            ->create($order, ['base_price' => 5000], 5000, null, 7, $admin->id);

        Notification::assertSentTo($customer, CustomQuoteReady::class, function (CustomQuoteReady $notification) use ($customer, $quote): bool {
            $mail = $notification->toMail($customer);

            $this->assertNotSame('', $mail->subject);
            $this->assertStringNotContainsString('24 hours', $mail->subject);

            foreach ($mail->introLines as $line) {
                $this->assertStringNotContainsString('24 hours', $line);
            }

            $validityLine = collect($mail->introLines)->first(
                fn (string $line): bool => str_contains($line, 'valid until') || str_contains($line, 'expired')
            );
            $this->assertNotNull($validityLine, 'The email must state the quote validity.');
            $this->assertStringContainsString($quote->valid_until->format('j M Y, g:i a'), $validityLine);

            return true;
        });
    }

    public function test_quote_email_says_expired_when_validity_has_passed(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $customer = $this->customer();
        $order = $this->submittedOrder($customer);

        $quote = app(CustomQuoteService::class)
            ->create($order, ['base_price' => 5000], 5000, null, 7, $admin->id);

        $quote->forceFill(['valid_until' => now()->subDay()])->save();

        Notification::assertSentTo($customer, CustomQuoteReady::class, function (CustomQuoteReady $notification) use ($customer): bool {
            $mail = $notification->toMail($customer);

            $validityLine = collect($mail->introLines)->first(
                fn (string $line): bool => str_contains($line, 'valid until') || str_contains($line, 'expired')
            );
            $this->assertNotNull($validityLine);
            $this->assertStringContainsString('expired', $validityLine);

            return true;
        });
    }

    public function test_quote_survives_a_mail_failure(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $order = $this->submittedOrder($customer);

        $this->swapFailingMailer();

        $quote = app(CustomQuoteService::class)
            ->create($order, ['base_price' => 5000], 5000, null, 7, $admin->id);

        $this->assertNotNull($quote->fresh(), 'The quote must be persisted even when mail fails.');
        $this->assertSame(5000.0, (float) $quote->total);
        $this->assertSame(CustomOrder::STATUS_QUOTED, $order->fresh()->status);
        $this->assertSame(5000.0, (float) $order->fresh()->total_amount);
        $this->assertDatabaseHas('custom_order_quotes', [
            'custom_order_id' => $order->id,
            'version' => 1,
        ]);
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
