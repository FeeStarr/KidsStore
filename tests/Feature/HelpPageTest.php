<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class HelpPageTest extends TestCase
{
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private function putScreenshot(string $name): void
    {
        File::ensureDirectoryExists(public_path('images/help'));
        File::put(public_path('images/help/' . $name), base64_decode(self::PNG));
    }

    private function deleteScreenshot(string $name): void
    {
        File::delete(public_path('images/help/' . $name));
    }

    public function test_help_page_renders_default_guide_with_toc(): void
    {
        $this->get(route('shop.help'))
            ->assertOk()
            ->assertSee('How to use this site')
            ->assertSee('On this page')
            ->assertSee('policy-section-1')
            ->assertSee('Create Your Account')
            ->assertSee('Explore Custom Creations');
    }

    public function test_help_page_has_nav_and_footer_links_active(): void
    {
        $this->get(route('shop.help'))
            ->assertOk()
            ->assertSee('<a class="dropdown-item active" href="' . route('shop.help') . '"><i class="bi bi-question-circle me-1"></i>Help &amp; Guide</a>', false)
            ->assertSee('<a href="' . route('shop.help') . '" class="text-decoration-none text-white-50 active">Help &amp; Guide</a>', false);
    }

    public function test_saved_text_replaces_default_guide(): void
    {
        Setting::set('help_guide', "MY OWN GUIDE\n1. First Step\nThis is my own text.");

        $this->get(route('shop.help'))
            ->assertOk()
            ->assertSee('First Step')
            ->assertSee('This is my own text.')
            ->assertDontSee('Create Your Account');
    }

    public function test_screenshot_attaches_to_section_by_number(): void
    {
        Setting::set('help_guide', "HELP\n97. Numbered Section\nBody text for the numbered section.");
        $this->putScreenshot('97.png');

        try {
            $this->get(route('shop.help'))
                ->assertOk()
                ->assertSee('images/help/97.png', false)
                ->assertSee('pp-figure', false);
        } finally {
            $this->deleteScreenshot('97.png');
        }
    }

    public function test_screenshot_attaches_to_section_by_heading_slug(): void
    {
        Setting::set('help_guide', "HELP\n1. Sample Heading Slug\nBody text for the slug section.");
        $this->putScreenshot('sample-heading-slug.png');

        try {
            $this->get(route('shop.help'))
                ->assertOk()
                ->assertSee('images/help/sample-heading-slug.png', false);
        } finally {
            $this->deleteScreenshot('sample-heading-slug.png');
        }
    }

    public function test_section_without_screenshot_renders_text_only(): void
    {
        Setting::set('help_guide', "HELP\n98. No Image Section\nBody text with no picture.");

        $this->get(route('shop.help'))
            ->assertOk()
            ->assertSee('No Image Section')
            ->assertSee('Body text with no picture.')
            ->assertDontSee('images/help/98');
    }

    public function test_help_page_is_in_sitemap(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('/help');
    }

    public function test_contact_cta_links_are_present(): void
    {
        $this->get(route('shop.help'))
            ->assertOk()
            ->assertSee(route('shop.order.lookup'))
            ->assertSee(route('shop.contact'));
    }
}
