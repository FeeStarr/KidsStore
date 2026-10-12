<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\DeliveryAgent;
use App\Models\DeliveryCharge;
use App\Models\DeliveryLocation;
use App\Models\User;
use App\Services\CustomOrderService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class CustomOrderDuplicateSubmissionTest extends TestCase
{
    private function customer(): User
    {
        return User::factory()->create(['role' => User::ROLE_CUSTOMER]);
    }

    private function storePayload(array $overrides = []): array
    {
        $location = DeliveryLocation::firstOrCreate(
            ['name' => 'Test City'],
            ['state' => 'Lagos', 'is_active' => true]
        );

        if (! DeliveryCharge::where('delivery_location_id', $location->id)->exists()) {
            $agent = DeliveryAgent::create([
                'name' => 'Test Agent',
                'account_number' => 'AG'.rand(10000, 99999),
                'phone' => '08012345678',
                'is_active' => true,
            ]);

            DeliveryCharge::create([
                'delivery_agent_id' => $agent->id,
                'delivery_location_id' => $location->id,
                'amount' => 1500,
                'is_active' => true,
            ]);
        }

        return array_merge([
            'child_name' => 'Test Child',
            'delivery_method' => 'delivery',
            'delivery_location_id' => $location->id,
            'delivery_address' => '123 Test Street',
            'primary_colour' => 'Blue',
            'standard_size' => '2-3 Years',
            'return_policy_acknowledged' => '1',
        ], $overrides);
    }

    public function test_replayed_submission_does_not_create_a_second_order(): void
    {
        $user = $this->customer();
        $token = (string) Str::uuid();
        $payload = $this->storePayload(['submission_token' => $token]);

        $first = $this->actingAs($user, 'web')
            ->post(route('shop.custom-frock.store'), $payload)
            ->assertRedirect();

        // The customer (or their browser/proxy) sends the exact same submit again.
        $second = $this->actingAs($user, 'web')
            ->post(route('shop.custom-frock.store'), $payload)
            ->assertRedirect();

        $this->assertSame(1, CustomOrder::where('user_id', $user->id)->count());
        $this->assertSame($first->getTargetUrl(), $second->getTargetUrl());

        $order = CustomOrder::where('user_id', $user->id)->firstOrFail();
        $this->assertSame($token, $order->submission_token);
        $this->assertSame(CustomOrder::STATUS_SUBMITTED, $order->status);
    }

    public function test_replayed_submission_reports_the_original_order_number(): void
    {
        $user = $this->customer();
        $payload = $this->storePayload(['submission_token' => (string) Str::uuid()]);

        $this->actingAs($user, 'web')->post(route('shop.custom-frock.store'), $payload);
        $response = $this->actingAs($user, 'web')->post(route('shop.custom-frock.store'), $payload);

        $response->assertSessionHas('success');
        $this->assertStringContainsString(
            CustomOrder::where('user_id', $user->id)->firstOrFail()->custom_order_number,
            session('success')
        );
    }

    public function test_many_replayed_submissions_yield_one_order(): void
    {
        $user = $this->customer();
        $payload = $this->storePayload(['submission_token' => (string) Str::uuid()]);

        foreach (range(1, 5) as $ignored) {
            $this->actingAs($user, 'web')
                ->post(route('shop.custom-frock.store'), $payload)
                ->assertRedirect();
        }

        $this->assertSame(1, CustomOrder::where('user_id', $user->id)->count());
    }

    public function test_distinct_tokens_still_create_separate_orders(): void
    {
        $user = $this->customer();

        $this->actingAs($user, 'web')
            ->post(route('shop.custom-frock.store'), $this->storePayload(['submission_token' => (string) Str::uuid()]))
            ->assertRedirect();

        $this->actingAs($user, 'web')
            ->post(route('shop.custom-frock.store'), $this->storePayload(['submission_token' => (string) Str::uuid()]))
            ->assertRedirect();

        $this->assertSame(2, CustomOrder::where('user_id', $user->id)->count());
        $this->assertSame(
            2,
            CustomOrder::where('user_id', $user->id)->whereNotNull('submission_token')->distinct('submission_token')->count()
        );
    }

    public function test_another_customers_token_is_not_reused(): void
    {
        $first = $this->customer();
        $second = $this->customer();
        $token = (string) Str::uuid();

        $this->actingAs($first, 'web')
            ->post(route('shop.custom-frock.store'), $this->storePayload(['submission_token' => $token]))
            ->assertRedirect();

        $this->actingAs($second, 'web')
            ->post(route('shop.custom-frock.store'), $this->storePayload(['submission_token' => $token, 'child_name' => 'Other Child']))
            ->assertRedirect();

        // Ownership scoping means the second customer's token cannot resolve to the
        // first customer's order, so it creates its own.
        $this->assertSame(1, CustomOrder::where('user_id', $first->id)->count());
        $this->assertSame(1, CustomOrder::where('user_id', $second->id)->count());
    }

    public function test_create_form_renders_a_submission_token(): void
    {
        $this->actingAsCustomer($this->customer());

        $response = $this->get(route('shop.custom-frock.create'))->assertOk();

        $response->assertSee('name="submission_token"', false);
        $this->assertNotEmpty($this->submissionTokenFrom($response->getContent()));
    }

    public function test_each_render_of_the_create_form_gets_a_new_token(): void
    {
        $user = $this->customer();
        $this->actingAsCustomer($user);

        $first = $this->submissionTokenFrom($this->get(route('shop.custom-frock.create'))->getContent());
        $second = $this->submissionTokenFrom($this->get(route('shop.custom-frock.create'))->getContent());

        $this->assertNotSame($first, $second);
    }

    public function test_store_without_a_token_still_creates_an_order(): void
    {
        $user = $this->customer();

        $this->actingAs($user, 'web')
            ->post(route('shop.custom-frock.store'), $this->storePayload())
            ->assertRedirect();

        $this->assertSame(1, CustomOrder::where('user_id', $user->id)->count());
        $this->assertNull(CustomOrder::where('user_id', $user->id)->firstOrFail()->submission_token);
    }

    public function test_generate_reference_skips_taken_numbers(): void
    {
        CustomOrder::create([
            'custom_order_number' => 'CF-000001',
            'user_id' => $this->customer()->id,
            'item_type' => 'frock',
            'status' => CustomOrder::STATUS_DRAFT,
            'delivery_method' => 'delivery',
        ]);

        $this->assertSame('CF-000002', CustomOrder::generateReference());
    }

    /**
     * Covers the race the pre-flight lookup cannot: a request whose lookup already
     * returned "not found" and which then loses the unique index to a sibling.
     */
    public function test_unique_index_violation_adopts_the_concurrently_created_order(): void
    {
        $user = $this->customer();
        $token = (string) Str::uuid();

        $winner = CustomOrder::create([
            'custom_order_number' => 'CF-000042',
            'submission_token' => $token,
            'user_id' => $user->id,
            'item_type' => 'frock',
            'status' => CustomOrder::STATUS_SUBMITTED,
            'child_name' => 'Test Child',
            'delivery_method' => 'pickup',
            'return_policy_acknowledged' => true,
        ]);

        // The genuine error the sibling request would hit on the unique index.
        $duplicate = $this->duplicateTokenInsert($user->id, $token);

        // The loser's own request landed after the winner committed, but its
        // in-flight lookup had already run and returned nothing.
        $service = $this->partialMock(CustomOrderService::class, function ($mock) use ($token, $winner, $duplicate) {
            // First call is the pre-flight lookup (miss); the second is the
            // fallback after losing the unique index race.
            $mock->shouldReceive('findBySubmissionToken')
                ->twice()
                ->with($token, Mockery::any())
                ->andReturn(null, $winner);

            $mock->shouldReceive('create')
                ->once()
                ->andThrow($duplicate);
        });

        $this->app->instance(CustomOrderService::class, $service);

        $response = $this->actingAs($user, 'web')
            ->post(route('shop.custom-frock.store'), $this->storePayload(['submission_token' => $token]));

        $response->assertRedirect(route('shop.custom-frock.show', $winner));
        $this->assertSame(1, CustomOrder::where('user_id', $user->id)->count());
    }

    /**
     * The database is the real guarantee: two copies arriving at once cannot both win.
     */
    public function test_database_rejects_a_second_row_for_the_same_customer_and_token(): void
    {
        $user = $this->customer();
        $token = (string) Str::uuid();

        CustomOrder::create([
            'custom_order_number' => 'CF-000100',
            'submission_token' => $token,
            'user_id' => $user->id,
            'item_type' => 'frock',
            'status' => CustomOrder::STATUS_DRAFT,
            'delivery_method' => 'delivery',
        ]);

        $this->expectException(QueryException::class);

        CustomOrder::create([
            'custom_order_number' => 'CF-000101',
            'submission_token' => $token,
            'user_id' => $user->id,
            'item_type' => 'frock',
            'status' => CustomOrder::STATUS_DRAFT,
            'delivery_method' => 'delivery',
        ]);
    }

    private function duplicateTokenInsert(int $userId, string $token): QueryException
    {
        try {
            CustomOrder::create([
                'custom_order_number' => 'CF-000'.($userId + 900),
                'submission_token' => $token,
                'user_id' => $userId,
                'item_type' => 'frock',
                'status' => CustomOrder::STATUS_DRAFT,
                'delivery_method' => 'delivery',
            ]);
        } catch (QueryException $e) {
            return $e;
        }

        $this->fail('Expected the unique submission_token index to reject the insert.');
    }

    private function submissionTokenFrom(string $html): ?string
    {
        return preg_match('/name="submission_token"[^>]*value="([^"]*)"/', $html, $m) ? $m[1] : null;
    }
}
