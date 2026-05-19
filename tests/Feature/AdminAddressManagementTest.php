<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAddressManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_user_addresses(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $user = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $this->actingAs($admin)
            ->post(route('admin.users.addresses.store', $user), [
                'label' => 'Home',
                'line1' => '12 Main Street',
                'city' => 'Lagos',
                'country' => 'Nigeria',
                'is_default' => true,
            ])
            ->assertRedirect();

        $address = Address::where('user_id', $user->id)->first();
        $this->assertNotNull($address);
        $this->assertTrue((bool) $address->is_default);

        $this->actingAs($admin)
            ->post(route('admin.users.addresses.default', [$user, $address]))
            ->assertRedirect();

        $this->actingAs($admin)
            ->delete(route('admin.users.addresses.destroy', [$user, $address]))
            ->assertRedirect();

        $this->assertDatabaseMissing('addresses', [
            'id' => $address->id,
        ]);
    }
}
