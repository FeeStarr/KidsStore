<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_profile_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('shop.account.profile'))
            ->assertStatus(200);
    }

    public function test_authenticated_user_can_update_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('shop.account.profile.update'), [
                'name' => 'Updated Name',
                'phone' => '1234567890',
                'address' => '12 Main Street',
                'first_name' => 'Updated',
                'last_name' => 'User',
                'bio' => 'Testing profile update.',
                'avatar_url' => 'https://example.com/avatar.png',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'phone' => '1234567890',
            'address' => '12 Main Street',
        ]);

        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
            'first_name' => 'Updated',
            'last_name' => 'User',
            'bio' => 'Testing profile update.',
        ]);
    }

    public function test_authenticated_user_can_manage_addresses(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('shop.account.addresses.store'), [
                'label' => 'Home',
                'line1' => '123 Home St',
                'city' => 'Lagos',
                'country' => 'Nigeria',
                'is_default' => true,
            ])
            ->assertRedirect();

        $address = Address::where('user_id', $user->id)->first();
        $this->assertNotNull($address);
        $this->assertTrue((bool) $address->is_default);

        $this->actingAs($user)
            ->post(route('shop.account.addresses.store'), [
                'label' => 'Work',
                'line1' => '456 Work Ave',
                'city' => 'Lagos',
                'country' => 'Nigeria',
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('shop.account.addresses.default', $address))
            ->assertRedirect();

        $this->assertDatabaseHas('addresses', [
            'id' => $address->id,
            'user_id' => $user->id,
            'is_default' => true,
        ]);

        $this->actingAs($user)
            ->delete(route('shop.account.addresses.destroy', $address))
            ->assertRedirect();

        $this->assertDatabaseMissing('addresses', [
            'id' => $address->id,
        ]);
    }
}
