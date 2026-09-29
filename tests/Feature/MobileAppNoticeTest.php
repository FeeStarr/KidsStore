<?php

namespace Tests\Feature;

use Tests\TestCase;

class MobileAppNoticeTest extends TestCase
{
    public function test_homepage_shows_mobile_app_in_progress_badge(): void
    {
        $this->get(route('shop.home'))
            ->assertOk()
            ->assertSee('Mobile app in progress');
    }

    public function test_footer_shows_mobile_app_notice_on_all_pages(): void
    {
        $this->get(route('shop.contact'))
            ->assertOk()
            ->assertSee('mobile app is')
            ->assertSee('in progress');
    }
}
