<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivacyPolicyPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_privacy_policy_page_renders_with_styled_layout(): void
    {
        Setting::set('privacy_policy', "We keep your data safe.\nContact us to delete it.");

        $this->get(route('shop.privacy-policy'))
            ->assertOk()
            ->assertSee('Privacy Policy')
            ->assertSee('Privacy at a glance')
            ->assertSee('Your data, your control')
            ->assertSee('We keep your data safe.')
            ->assertSee('Contact us to delete it.')
            ->assertSee('privacy-policy-content');
    }

    public function test_privacy_policy_page_renders_fallback_without_content(): void
    {
        Setting::set('privacy_policy', '');

        $this->get(route('shop.privacy-policy'))
            ->assertOk()
            ->assertSee('Our promise')
            ->assertSee('Privacy at a glance');
    }
}
