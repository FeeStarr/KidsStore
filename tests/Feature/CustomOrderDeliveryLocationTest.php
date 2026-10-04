<?php

namespace Tests\Feature;

use App\Models\CustomOrder;
use App\Models\DeliveryAgent;
use App\Models\DeliveryCharge;
use App\Models\DeliveryLocation;
use App\Models\PickupStation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomOrderDeliveryLocationTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): User
    {
        return User::factory()->create(['role' => User::ROLE_CUSTOMER]);
    }

    private function makeLocation(string $name = 'Ikeja'): DeliveryLocation
    {
        return DeliveryLocation::create(['name' => $name, 'state' => 'Lagos', 'is_active' => true]);
    }

    private function makeCharge(DeliveryLocation $location, float $amount = 2500): DeliveryCharge
    {
        $agent = DeliveryAgent::create([
            'name' => 'Test Agent',
            'account_number' => 'AG' . rand(10000, 99999),
            'phone' => '08012345678',
            'is_active' => true,
        ]);

        return DeliveryCharge::create([
            'delivery_agent_id' => $agent->id,
            'delivery_location_id' => $location->id,
            'amount' => $amount,
            'is_active' => true,
        ]);
    }

    private function makeStation(): PickupStation
    {
        return PickupStation::create([
            'name' => 'Test Station',
            'address' => '1 Station Road',
            'email' => 'station@test.local',
            'is_active' => true,
            'is_available' => true,
        ]);
    }

    private function storePayload(array $overrides = []): array
    {
        return array_merge([
            'child_name' => 'Test Child',
            'delivery_method' => 'delivery',
            'delivery_address' => '123 Test Street',
            'primary_colour' => 'Blue',
            'standard_size' => '2-3 Years',
            'return_policy_acknowledged' => '1',
        ], $overrides);
    }

    public function test_create_form_shows_delivery_locations_with_prices(): void
    {
        $location = $this->makeLocation('Ikeja');
        $this->makeCharge($location, 2500);
        $inactive = DeliveryLocation::create(['name' => 'Hidden Spot', 'state' => 'Lagos', 'is_active' => false]);

        $response = $this->actingAs($this->customer())
            ->get(route('shop.custom-frock.create'));

        $response->assertOk();
        $response->assertSee('Ikeja');
        $response->assertSee('₦2,500.00');
        $response->assertSee('delivery-location-select');
        $response->assertDontSee('Hidden Spot');
    }

    public function test_store_persists_delivery_location_and_fee(): void
    {
        $location = $this->makeLocation('Ikeja');
        $this->makeCharge($location, 2500);

        $this->actingAs($this->customer())
            ->post(route('shop.custom-frock.store'), $this->storePayload(['delivery_location_id' => $location->id]))
            ->assertRedirect();

        $this->assertDatabaseHas('custom_orders', [
            'delivery_location_id' => $location->id,
            'delivery_fee' => 2500,
        ]);
    }

    public function test_store_requires_location_for_home_delivery(): void
    {
        $this->actingAs($this->customer())
            ->from(route('shop.custom-frock.create'))
            ->post(route('shop.custom-frock.store'), $this->storePayload())
            ->assertRedirect(route('shop.custom-frock.create'))
            ->assertSessionHasErrors('delivery_location_id');
    }

    public function test_store_does_not_require_location_for_pickup(): void
    {
        $this->actingAs($this->customer())
            ->post(route('shop.custom-frock.store'), $this->storePayload([
                'delivery_method' => 'pickup',
                'pickup_station_id' => $this->makeStation()->id,
                'delivery_location_id' => null,
                'delivery_address' => null,
            ]))
            ->assertRedirect();

        $order = CustomOrder::where('child_name', 'Test Child')->firstOrFail();
        $this->assertSame('pickup', $order->delivery_method);
        $this->assertNull($order->delivery_location_id);
        $this->assertEquals(0, (float) $order->delivery_fee);
    }

    public function test_store_keeps_location_and_fee_out_for_pickup(): void
    {
        $location = $this->makeLocation('Ikeja');
        $this->makeCharge($location, 2500);

        $this->actingAs($this->customer())
            ->post(route('shop.custom-frock.store'), $this->storePayload([
                'delivery_method' => 'pickup',
                'pickup_station_id' => $this->makeStation()->id,
                'delivery_location_id' => $location->id,
                'delivery_address' => null,
            ]))
            ->assertRedirect();

        $order = CustomOrder::where('child_name', 'Test Child')->firstOrFail();
        $this->assertNull($order->delivery_location_id);
        $this->assertEquals(0, (float) $order->delivery_fee);
    }

    public function test_delivery_without_available_agent_is_rejected(): void
    {
        $location = $this->makeLocation('Ikeja');

        $this->actingAs($this->customer())
            ->from(route('shop.custom-frock.create'))
            ->post(route('shop.custom-frock.store'), $this->storePayload(['delivery_location_id' => $location->id]))
            ->assertRedirect(route('shop.custom-frock.create'))
            ->assertSessionHasErrors('delivery_location_id');

        $this->assertDatabaseCount('custom_orders', 0);
    }

    public function test_delivery_with_inactive_agent_is_rejected(): void
    {
        $location = $this->makeLocation('Ikeja');
        $charge = $this->makeCharge($location);
        $charge->agent->update(['is_active' => false]);

        $this->actingAs($this->customer())
            ->from(route('shop.custom-frock.create'))
            ->post(route('shop.custom-frock.store'), $this->storePayload(['delivery_location_id' => $location->id]))
            ->assertRedirect(route('shop.custom-frock.create'))
            ->assertSessionHasErrors('delivery_location_id');

        $this->assertDatabaseCount('custom_orders', 0);
    }

    public function test_delivery_with_available_agent_stores_fee(): void
    {
        $location = $this->makeLocation('Ikeja');
        $this->makeCharge($location, 3000);

        $this->actingAs($this->customer())
            ->post(route('shop.custom-frock.store'), $this->storePayload(['delivery_location_id' => $location->id]))
            ->assertRedirect();

        $order = CustomOrder::where('child_name', 'Test Child')->firstOrFail();
        $this->assertEquals($location->id, $order->delivery_location_id);
        $this->assertEquals(3000, (float) $order->delivery_fee);
    }
}
