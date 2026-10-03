<?php

namespace Tests\Feature;

use Tests\TestCase;

class HomeHeroTest extends TestCase
{
    public function test_see_deals_button_links_to_the_deals_page(): void
    {
        $this->get(route('shop.home'))
            ->assertOk()
            ->assertSee('<a href="' . route('shop.deals.index') . '" class="btn btn-outline-light btn-lg"', false)
            ->assertSee('See deals');
    }

    public function test_shop_now_button_links_to_the_shop(): void
    {
        $this->get(route('shop.home'))
            ->assertOk()
            ->assertSee('<a href="' . route('shop.products.index') . '" class="btn btn-light btn-lg me-2"', false)
            ->assertSee('Shop now');
    }
}
