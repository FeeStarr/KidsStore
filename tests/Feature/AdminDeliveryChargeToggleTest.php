<?php

namespace Tests\Feature;

use App\Models\DeliveryAgent;
use App\Models\DeliveryCharge;
use App\Models\DeliveryLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDeliveryChargeToggleTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function makeCharge(): DeliveryCharge
    {
        $location = DeliveryLocation::create(['name' => 'Ikeja', 'state' => 'Lagos', 'is_active' => true]);
        $agent = DeliveryAgent::create([
            'name' => 'Toggle Agent',
            'account_number' => 'AG' . rand(10000, 99999),
            'phone' => '08012345678',
            'is_active' => true,
        ]);

        return DeliveryCharge::create([
            'delivery_agent_id' => $agent->id,
            'delivery_location_id' => $location->id,
            'amount' => 2500,
            'is_active' => true,
        ]);
    }

    public function test_deactivating_a_charge_does_not_require_other_details(): void
    {
        $charge = $this->makeCharge();

        $response = $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.delivery-charges.toggle-active', $charge));

        $response->assertRedirect();
        $this->assertDatabaseHas('delivery_charges', [
            'id' => $charge->id,
            'is_active' => false,
        ]);
    }

    public function test_toggling_back_reactivates_the_charge(): void
    {
        $charge = $this->makeCharge();

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.delivery-charges.toggle-active', $charge))
            ->assertRedirect();

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.delivery-charges.toggle-active', $charge))
            ->assertRedirect();

        $this->assertDatabaseHas('delivery_charges', [
            'id' => $charge->id,
            'is_active' => true,
        ]);
    }

    public function test_toggle_changes_only_the_active_flag(): void
    {
        $charge = $this->makeCharge();

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.delivery-charges.toggle-active', $charge));

        $fresh = $charge->fresh();
        $this->assertFalse($fresh->is_active);
        $this->assertSame($charge->amount, $fresh->amount);
        $this->assertSame($charge->delivery_agent_id, $fresh->delivery_agent_id);
        $this->assertSame($charge->delivery_location_id, $fresh->delivery_location_id);
    }

    public function test_index_shows_the_toggle_control(): void
    {
        $charge = $this->makeCharge();

        $response = $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.delivery-charges.index'));

        $response->assertOk();
        $response->assertSee('toggle-active');
        $response->assertSee('Deactivate');
    }
}
