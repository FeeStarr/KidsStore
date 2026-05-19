<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_users_index(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertStatus(200);
    }

    public function test_admin_can_assign_role_and_toggle_active(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $user = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'is_active' => true]);

        $this->actingAs($admin)
            ->post(route('admin.users.assign-role', $user), [
                'role' => User::ROLE_VENDOR,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => User::ROLE_VENDOR,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.toggle-active', $user))
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_active' => false,
        ]);
    }

    public function test_admin_can_update_profile_details(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $user = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $this->actingAs($admin)
            ->put(route('admin.users.profile.update', $user), [
                'name' => 'Updated User',
                'phone' => '555-0100',
                'address' => '10 Admin Way',
                'first_name' => 'Updated',
                'last_name' => 'Customer',
                'bio' => 'Admin updated profile',
                'avatar_url' => 'https://example.com/avatar.png',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated User',
            'phone' => '555-0100',
            'address' => '10 Admin Way',
        ]);

        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
            'first_name' => 'Updated',
            'last_name' => 'Customer',
            'bio' => 'Admin updated profile',
        ]);
    }
}
