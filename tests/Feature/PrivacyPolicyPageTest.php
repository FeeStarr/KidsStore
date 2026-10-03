<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivacyPolicyPageTest extends TestCase
{
    use RefreshDatabase;

    private const SAMPLE_POLICY = <<<TXT
KIDSFLAIRR PRIVACY POLICY
Last Updated: 1 October 2026
KidsFlairr ("KidsFlairr", "we", "us", or "our") respects your privacy and is committed to protecting your personal information.
1. Information We Collect
Depending on how you use KidsFlairr, we may collect:
\u{25AA}Name and contact details such as email address and phone number;
\u{25A1}Order and purchase information;
We aim to collect only information that is reasonably necessary for the relevant purpose.
2. How We Use Your Information
We may use your information to:
\u{2022}Create and manage your account;
TXT;

    public function test_privacy_policy_renders_structured_sections_with_table_of_contents(): void
    {
        Setting::set('privacy_policy', self::SAMPLE_POLICY);

        $this->get(route('shop.privacy-policy'))
            ->assertOk()
            ->assertSee('Privacy Policy')
            ->assertSee('Your data, your control')
            ->assertSee('KIDSFLAIRR PRIVACY POLICY')
            ->assertSee('Last updated: 1 October 2026')
            ->assertSee('On this page')
            ->assertSee('policy-section-1')
            ->assertSee('policy-section-2')
            ->assertSee('Information We Collect')
            ->assertSee('How We Use Your Information')
            ->assertSee('pp-list')
            ->assertSee('pp-doc-title')
            ->assertSee('respects your privacy and is committed');
    }

    public function test_bullet_markers_are_replaced_by_styled_list_items(): void
    {
        Setting::set('privacy_policy', self::SAMPLE_POLICY);

        $html = $this->get(route('shop.privacy-policy'))->assertOk()->getContent();

        $this->assertStringContainsString('<ul class="pp-list">', $html);
        $this->assertStringNotContainsString("\u{25AA}", $html);
        $this->assertStringNotContainsString("\u{25A1}", $html);
        $this->assertStringNotContainsString("\u{2022}", $html);
        $this->assertStringContainsString('<li>Name and contact details', $html);
    }

    public function test_privacy_policy_page_renders_fallback_without_content(): void
    {
        Setting::set('privacy_policy', '');

        $this->get(route('shop.privacy-policy'))
            ->assertOk()
            ->assertSee('Our promise')
            ->assertSee('Privacy at a glance')
            ->assertDontSee('On this page');
    }
}
