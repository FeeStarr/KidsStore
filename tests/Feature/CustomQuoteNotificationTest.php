<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\CustomOrderQuote;
use App\Models\User;
use App\Notifications\CustomQuoteReady;
use App\Services\CustomQuoteService;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CustomQuoteNotificationTest extends TestCase
{
    private function customer(): User
    {
        return User::factory()->create(['role' => User::ROLE_CUSTOMER, 'is_active' => true]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN, 'is_active' => true]);
    }

    private function order(User $customer): CustomOrder
    {
        return CustomOrder::create([
            'custom_order_number' => 'CF-'.str_pad((string) (CustomOrder::count() + 1), 6, '0', STR_PAD_LEFT),
            'user_id' => $customer->id,
            'item_type' => 'frock',
            'status' => CustomOrder::STATUS_SUBMITTED,
            'child_name' => 'Amara',
            'delivery_method' => 'pickup',
            'return_policy_acknowledged' => true,
        ]);
    }

    private function breakdown(array $overrides = []): array
    {
        return array_merge([
            'base_price' => 20000,
            'fabric_cost' => 5000,
            'customization_cost' => 0,
            'embellishment_cost' => 0,
            'measurement_fee' => 0,
            'rush_fee' => 0,
            'delivery_fee' => 0,
            'discount' => 0,
        ], $overrides);
    }

    /**
     * The admin picks 1-90 days (default 7). The email used to hard-code
     * "valid for 24 hours" regardless, telling customers the wrong expiry.
     */
    public function test_quote_email_states_the_real_expiry_not_a_fixed_window(): void
    {
        $customer = $this->customer();
        $order = $this->order($customer);

        $quote = app(CustomQuoteService::class)->create($order, $this->breakdown(), 25000, null, 14, $this->admin()->id);

        $lines = collect((new CustomQuoteReady($order, $quote))->toMail($customer)->introLines)
            ->implode(' | ');

        $this->assertStringContainsString('valid until', $lines);
        $this->assertStringNotContainsString('24 hours', $lines);
        $this->assertStringContainsString(
            $quote->valid_until->format('j M Y'),
            $lines,
            'The email should quote the quote\'s own expiry date.'
        );
    }

    public function test_expired_quote_email_says_so(): void
    {
        $customer = $this->customer();
        $order = $this->order($customer);

        $quote = CustomOrderQuote::create([
            'custom_order_id' => $order->id,
            'version' => 1,
            'base_price' => 20000,
            'total' => 25000,
            'valid_until' => now()->subDay(),
            'status' => CustomOrderQuote::STATUS_DRAFT,
            'created_by' => $this->admin()->id,
        ]);

        $lines = collect((new CustomQuoteReady($order, $quote))->toMail($customer)->introLines)->implode(' | ');

        $this->assertStringContainsString('expired', $lines);
    }

    public function test_customer_is_notified_when_a_quote_is_created(): void
    {
        Notification::fake();

        $customer = $this->customer();
        $order = $this->order($customer);

        app(CustomQuoteService::class)->create($order, $this->breakdown(), 25000, null, 7, $this->admin()->id);

        Notification::assertSentTo($customer, CustomQuoteReady::class);
    }

    /**
     * Regression: notify() sat inside DB::transaction(), so any mail failure
     * (SMTP timeout, bad credentials) rolled the whole quote back and the admin's
     * work vanished. The quote must survive a failed send.
     *
     * MailChannel resolves the mailer through the MailFactory contract
     * ($this->mailer->mailer(...)->send()), NOT the Notification facade - so the
     * mailer itself is stubbed to throw. Mocking Notification::send() here would
     * never fire and the test would pass without exercising anything.
     */
    public function test_quote_survives_a_mail_failure(): void
    {
        $this->swapFailingMailer();

        $customer = $this->customer();
        $order = $this->order($customer);
        $admin = $this->admin();

        $quote = app(CustomQuoteService::class)->create($order, $this->breakdown(), 25000, null, 7, $admin->id);

        $this->assertNotNull($quote->fresh(), 'The quote must be persisted even when mail fails.');
        $this->assertSame(25000.0, (float) $quote->total);
        $this->assertSame(CustomOrder::STATUS_QUOTED, $order->fresh()->status);
        $this->assertSame(25000.0, (float) $order->fresh()->total_amount);
        $this->assertDatabaseCount('custom_order_quotes', 1);
    }

    /**
     * Sanity check on the harness above: prove the stub actually raises, so a green
     * "survives a mail failure" test cannot be passing for the wrong reason.
     */
    public function test_failing_mailer_stub_actually_throws(): void
    {
        $this->swapFailingMailer();

        $customer = $this->customer();
        $order = $this->order($customer);
        $quote = CustomOrderQuote::create([
            'custom_order_id' => $order->id,
            'version' => 1,
            'base_price' => 20000,
            'total' => 25000,
            'valid_until' => now()->addDays(7),
            'status' => CustomOrderQuote::STATUS_DRAFT,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SMTP connection refused');

        $customer->notify(new CustomQuoteReady($order, $quote));
    }

    /**
     * The administrator's own route, not just the service, must tell the customer
     * that their quote is waiting. The customer is told to approve it, so the mail is
     * the thing that moves the request forward.
     */
    public function test_quoting_from_the_admin_page_emails_the_customer(): void
    {
        Notification::fake();

        $customer = $this->customer();
        $order = $this->order($customer);
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.custom-orders.quote', $order), $this->quotePayload())
            ->assertRedirect()
            ->assertSessionHas('success');

        Notification::assertSentTo($customer, CustomQuoteReady::class);
    }

    /**
     * A quote the customer never heard about is a request that simply stops, so the
     * administrator has to be told when the mail did not go out. The quote itself
     * still exists.
     */
    public function test_the_admin_is_told_when_the_customer_could_not_be_emailed(): void
    {
        $this->swapFailingMailer();

        $customer = $this->customer();
        $order = $this->order($customer);
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.custom-orders.quote', $order), $this->quotePayload())
            ->assertSessionHas('error');

        $this->assertDatabaseCount('custom_order_quotes', 1);
    }

    /**
     * The outcome has to be visible on the page the administrator is sent back to,
     * or an action on this page gives no feedback at all.
     */
    public function test_the_admin_page_shows_what_happened(): void
    {
        Notification::fake();

        $customer = $this->customer();
        $order = $this->order($customer);
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.custom-orders.quote', $order), $this->quotePayload());

        $this->actingAs($admin, 'admin')
            ->get(route('admin.custom-orders.show', $order))
            ->assertOk()
            ->assertSee('Quote created and sent to customer.');
    }

    private function quotePayload(): array
    {
        return [
            'base_price' => 20000,
            'fabric_cost' => 5000,
            'customization_cost' => 0,
            'embellishment_cost' => 0,
            'measurement_fee' => 0,
            'rush_fee' => 0,
            'delivery_fee' => 0,
            'discount' => 0,
            'total' => 25000,
            'notes' => null,
            'valid_days' => 7,
        ];
    }

    /**
     * The customer has a copy of the quote, and so does the team.
     *
     * The customer is being asked to approve something, so somebody has to be able
     * to see what they were told - and the customer can see who is dealing with
     * them.
     */
    public function test_the_admin_is_copied_on_the_quote_email(): void
    {
        $customer = $this->customer();
        $order = $this->order($customer);
        $admin = $this->admin();

        $mail = (new CustomQuoteReady($order, $this->quote($order)))->toMail($customer);

        $bcc = $this->bccAddresses($mail);

        $this->assertContains(
            $admin->email,
            $bcc,
            'the admin must be copied on the quote email to the customer'
        );
        $this->assertContains(config('emails.orders'), $bcc);
    }

    /**
     * An email already addressed to the team is not copied onto itself.
     */
    public function test_an_admin_is_not_copied_the_email_they_are_already_receiving(): void
    {
        $customer = $this->customer();
        $order = $this->order($customer);
        $admin = $this->admin();

        $mail = (new CustomQuoteReady($order, $this->quote($order)))->toMail($admin);

        $this->assertNotContains(
            $admin->email,
            $this->bccAddresses($mail),
            'an admin must not receive their own copy twice'
        );
    }

    private function quote(CustomOrder $order): CustomOrderQuote
    {
        return CustomOrderQuote::create([
            'custom_order_id' => $order->id,
            'version' => 1,
            'base_price' => 20000,
            'total' => 25000,
            'valid_until' => now()->addDays(7),
            'status' => CustomOrderQuote::STATUS_DRAFT,
        ]);
    }

    /**
     * The addresses a mail message is blind-copied to.
     *
     * @return array<int, string>
     */
    private function bccAddresses(\Illuminate\Notifications\Messages\MailMessage $mail): array
    {
        return array_map(
            fn ($entry) => is_array($entry) ? ($entry[0] ?? $entry['email'] ?? null) : $entry,
            $mail->bcc
        );
    }

    private function swapFailingMailer(): void
    {
        app()->instance('mail.manager', new class implements \Illuminate\Contracts\Mail\Factory
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

    public function test_quote_creation_through_the_admin_route_persists(): void
    {
        Notification::fake();

        $customer = $this->customer();
        $order = $this->order($customer);
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.custom-orders.quote', $order), $this->breakdown() + [
                'total' => 25000,
                'notes' => 'Fabric is silk.',
                'valid_days' => 10,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $quote = CustomOrderQuote::where('custom_order_id', $order->id)->firstOrFail();

        $this->assertSame(25000.0, (float) $quote->total);
        $this->assertEqualsWithDelta(
            now()->addDays(10)->timestamp,
            $quote->valid_until->timestamp,
            60
        );
        $this->assertSame(CustomOrder::STATUS_QUOTED, $order->fresh()->status);
    }
}