<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AdminHelpEditorTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    public function test_admin_can_view_help_editor(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.help.edit'))
            ->assertOk()
            ->assertSee('Help & Guide')
            ->assertSee('Guide Content')
            ->assertSee('Screenshots');
    }

    public function test_admin_can_update_help_text(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->put(route('admin.help.update'), [
                'help_guide' => "UPDATED GUIDE\n1. Updated Step\nFresh instructions for customers.",
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->get(route('shop.help'))
            ->assertOk()
            ->assertSee('Updated Step')
            ->assertSee('Fresh instructions for customers.')
            ->assertDontSee('Create Your Account');
    }

    public function test_invalid_help_update_is_rejected(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->put(route('admin.help.update'), [])
            ->assertSessionHasErrors('help_guide');
    }

    public function test_guest_cannot_access_help_editor(): void
    {
        $this->get(route('admin.help.edit'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_customer_cannot_access_help_editor(): void
    {
        $this->actingAs($this->makeCustomer())
            ->get(route('admin.help.edit'))
            ->assertRedirect();
    }
}
