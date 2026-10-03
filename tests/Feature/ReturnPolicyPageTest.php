<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReturnPolicyPageTest extends TestCase
{
    use RefreshDatabase;

    private const SAMPLE_POLICY = <<<TXT
KIDSFLAIRR RETURN POLICY
Last Updated: 1 October 2026
We want you to love what you ordered. If something is wrong, here is how returns and refunds work at KidsFlairr.
1. Eligibility
To be eligible for a return:
▪The item must be unused and in its original packaging;
▪The request must be raised within the return window for that reason;
□Custom frocks are made-to-order and cannot be returned for change of mind;
2. How to Request a Return
Open your delivered order and press the Request a Refund button, then pick the reason and add photos.
TXT;

    public function test_return_policy_renders_structured_sections_with_table_of_contents(): void
    {
        Setting::set('return_policy', self::SAMPLE_POLICY);

        $this->get(route('shop.return-policy'))
            ->assertOk()
            ->assertSee('Return Policy')
            ->assertSee('KIDSFLAIRR RETURN POLICY')
            ->assertSee('Last updated: 1 October 2026')
            ->assertSee('On this page')
            ->assertSee('policy-section-1')
            ->assertSee('policy-section-2')
            ->assertSee('Eligibility')
            ->assertSee('How to Request a Return')
            ->assertSee('pp-list')
            ->assertSee('Return windows at a glance')
            ->assertSee('press the Request a Refund button');
    }

    public function test_bullet_markers_are_replaced_by_styled_list_items(): void
    {
        Setting::set('return_policy', self::SAMPLE_POLICY);

        $html = $this->get(route('shop.return-policy'))->assertOk()->getContent();

        $this->assertStringContainsString('<ul class="pp-list">', $html);
        $this->assertStringNotContainsString("\u{25AA}", $html);
        $this->assertStringNotContainsString("\u{25A1}", $html);
        $this->assertStringContainsString('<li>The item must be unused', $html);
    }

    public function test_return_policy_page_renders_fallback_with_windows(): void
    {
        Setting::set('return_policy', '');

        $this->get(route('shop.return-policy'))
            ->assertOk()
            ->assertSee('Our promise')
            ->assertSee('General window')
            ->assertSee('Return windows at a glance')
            ->assertDontSee('On this page');
    }
}
