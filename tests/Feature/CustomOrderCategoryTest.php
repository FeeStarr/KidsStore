<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\DeliveryLocation;
use App\Models\User;
use Tests\TestCase;

class CustomOrderCategoryTest extends TestCase
{
    private function customer(): User
    {
        return User::factory()->create(['role' => User::ROLE_CUSTOMER]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function makeOrder(User $user, array $overrides = []): CustomOrder
    {
        return CustomOrder::create(array_merge([
            'custom_order_number'      => 'CO-TEST-' . uniqid(),
            'user_id'                  => $user->id,
            'item_type'                => 'frock',
            'status'                   => CustomOrder::STATUS_SUBMITTED,
            'child_name'               => 'Amara',
            'delivery_method'          => 'delivery',
            'delivery_address'         => '12 Test Street',
            'return_policy_acknowledged' => true,
        ], $overrides));
    }

    private function storePayload(array $overrides = []): array
    {
        $location = DeliveryLocation::firstOrCreate(
            ['name' => 'Test City'],
            ['state' => 'Lagos', 'is_active' => true]
        );

        if (! \App\Models\DeliveryCharge::where('delivery_location_id', $location->id)->exists()) {
            $agent = \App\Models\DeliveryAgent::create([
                'name'           => 'Test Agent',
                'account_number' => 'AG' . rand(10000, 99999),
                'phone'          => '08012345678',
                'is_active'      => true,
            ]);

            \App\Models\DeliveryCharge::create([
                'delivery_agent_id'    => $agent->id,
                'delivery_location_id' => $location->id,
                'amount'               => 1500,
                'is_active'            => true,
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

    public function test_store_persists_selected_category(): void
    {
        $user = $this->customer();

        $this->actingAs($user, 'web')
            ->post(route('shop.custom-frock.store'), $this->storePayload(['category' => 'princess']))
            ->assertRedirect();

        $this->assertDatabaseHas('custom_orders', [
            'user_id' => $user->id,
            'category' => 'princess',
        ]);
    }

    public function test_store_defaults_to_null_category(): void
    {
        $user = $this->customer();

        $this->actingAs($user, 'web')
            ->post(route('shop.custom-frock.store'), $this->storePayload())
            ->assertRedirect();

        $order = CustomOrder::where('user_id', $user->id)->firstOrFail();
        $this->assertNull($order->category);
    }

    public function test_store_rejects_unknown_category(): void
    {
        $user = $this->customer();

        $this->actingAs($user, 'web')
            ->post(route('shop.custom-frock.store'), $this->storePayload(['category' => 'not_a_category']))
            ->assertInvalid('category');
    }

    public function test_create_form_shows_nullable_category_select(): void
    {
        $this->actingAsCustomer($this->customer());

        $this->get(route('shop.custom-frock.create'))
            ->assertOk()
            ->assertSee('No category')
            ->assertSee('Princess Dresses')
            ->assertSee('Special Occasion');
    }

    public function test_admin_index_filters_by_category(): void
    {
        $princessOrder = $this->makeOrder($this->customer(), ['category' => 'princess']);
        $ankaraOrder = $this->makeOrder($this->customer(), ['category' => 'ankara']);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.custom-orders.index', ['category' => 'princess']))
            ->assertOk()
            ->assertSee($princessOrder->custom_order_number)
            ->assertDontSee($ankaraOrder->custom_order_number);
    }

    public function test_admin_show_displays_category_label(): void
    {
        $order = $this->makeOrder($this->customer(), ['category' => 'birthday']);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.custom-orders.show', $order))
            ->assertOk()
            ->assertSee('Category:')
            ->assertSee('Birthday Dresses');
    }

    public function test_customer_show_displays_category_label(): void
    {
        $order = $this->makeOrder($this->customer(), ['category' => 'ankara']);

        $this->actingAs($order->user, 'web')
            ->get(route('shop.custom-frock.show', $order))
            ->assertOk()
            ->assertSee('Category:')
            ->assertSee('Ankara');
    }

    public function test_show_displays_dash_when_category_missing(): void
    {
        $order = $this->makeOrder($this->customer());

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.custom-orders.show', $order))
            ->assertOk()
            ->assertSee('Category:');
    }
}
