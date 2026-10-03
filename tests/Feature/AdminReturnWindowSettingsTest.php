<?php

namespace Tests\Feature;

use App\Models\RefundRequest;
use App\Models\Setting;
use App\Models\User;
use Tests\TestCase;

class AdminReturnWindowSettingsTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    public function test_return_policy_page_shows_return_window_fields(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.return-policy.edit'))
            ->assertOk()
            ->assertSee('Return Windows')
            ->assertSee('return_window_wrong_item')
            ->assertSee('return_window_wrong_color')
            ->assertSee('return_window_incomplete')
            ->assertDontSee('return_window_not_as_described')
            ->assertSee('return_window_damaged')
            ->assertDontSee('return_window_changed_mind')
            ->assertDontSee('return_window_default');
    }

    public function test_updating_return_windows_saves_and_applies_everywhere(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->put(route('admin.return-policy.update'), [
                'return_policy'          => 'Our return policy text.',
                'return_window_damaged'  => 1,
                'return_window_incomplete' => 4,
            ])
            ->assertSessionHas('success');

        $this->assertEquals(1.0, (float) Setting::get('return_window_damaged'));
        $this->assertEquals(4.0, (float) Setting::get('return_window_incomplete'));

        $this->assertSame(24, RefundRequest::timeLimitHours('damaged'));
        $this->assertSame(96, RefundRequest::timeLimitHours('incomplete_order'));
        // Untouched reasons keep their defaults.
        $this->assertSame(24, RefundRequest::timeLimitHours('missing_item'));
        $this->assertSame(168, RefundRequest::defaultWindowHours());

        $this->actingAs($this->admin(), 'web')
            ->get(route('shop.return-policy'))
            ->assertOk()
            ->assertSee('Return windows at a glance')
            ->assertSee('>1 day<', false)
            ->assertSee('>4 days<', false);
    }

    public function test_invalid_return_window_value_is_rejected(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->from(route('admin.return-policy.edit'))
            ->put(route('admin.return-policy.update'), [
                'return_policy'         => 'Our return policy text.',
                'return_window_damaged' => 'not-a-number',
            ])
            ->assertSessionHasErrors('return_window_damaged');

        $this->assertNull(Setting::get('return_window_damaged'));
        $this->assertSame(48, RefundRequest::timeLimitHours('damaged'));
    }
}
