<?php

namespace Tests\Feature;

use Tests\TestCase;

class ShopNavActiveStateTest extends TestCase
{
    private function home_link(bool $active): string
    {
        return '<a class="nav-link' . ($active ? ' active' : ' ') . '" href="' . route('shop.home') . '">Home</a>';
    }

    private function shop_link(bool $active): string
    {
        return '<a class="nav-link' . ($active ? ' active' : ' ') . '" href="' . route('shop.products.index') . '">Shop</a>';
    }

    public function test_home_link_is_active_on_home_page(): void
    {
        $this->get(route('shop.home'))
            ->assertOk()
            ->assertSee($this->home_link(true), false)
            ->assertSee($this->shop_link(false), false)
            ->assertDontSee($this->home_link(false), false);
    }

    public function test_shop_link_is_active_on_shop_pages(): void
    {
        $this->get(route('shop.products.index'))
            ->assertOk()
            ->assertSee($this->shop_link(true), false)
            ->assertSee($this->home_link(false), false)
            ->assertDontSee($this->shop_link(false), false);
    }

    public function test_deals_link_is_active_on_deals_page(): void
    {
        $this->get(route('shop.deals.index'))
            ->assertOk()
            ->assertSee('<a class="nav-link active" href="' . route('shop.deals.index') . '"><i class="bi bi-fire me-1"></i>Deals</a>', false)
            ->assertSee($this->shop_link(false), false);
    }

    public function test_more_dropdown_is_active_on_about_page(): void
    {
        $this->get(route('shop.about'))
            ->assertOk()
            ->assertSee('<a class="nav-link dropdown-toggle active" href="#" data-bs-toggle="dropdown">More</a>', false)
            ->assertSee('<a class="dropdown-item active" href="' . route('shop.about') . '">About</a>', false)
            ->assertSee('<a class="dropdown-item " href="' . route('shop.contact') . '">Contact</a>', false);
    }

    public function test_track_order_item_is_active_on_order_lookup_page(): void
    {
        $this->get(route('shop.order.lookup'))
            ->assertOk()
            ->assertSee('<a class="nav-link dropdown-toggle active" href="#" data-bs-toggle="dropdown">More</a>', false)
            ->assertSee('<a class="dropdown-item active" href="' . route('shop.order.lookup') . '"><i class="bi bi-box-seam me-1"></i>Track Order</a>', false);
    }

    public function test_return_policy_link_is_active_in_footer_on_return_policy_page(): void
    {
        $this->get(route('shop.return-policy'))
            ->assertOk()
            ->assertSee('<a href="' . route('shop.return-policy') . '" class="text-decoration-none text-white-50 active">Return Policy</a>', false)
            ->assertSee('<a href="' . route('shop.privacy-policy') . '" class="text-decoration-none text-white-50 ">Privacy Policy</a>', false);
    }

    public function test_no_footer_link_is_active_on_home_page(): void
    {
        $this->get(route('shop.home'))
            ->assertOk()
            ->assertDontSee('text-white-50 active', false);
    }

    public function test_login_link_is_active_on_login_page(): void
    {
        $this->get(route('shop.login'))
            ->assertOk()
            ->assertSee('<a class="nav-link active" href="' . route('shop.login') . '">Login</a>', false)
            ->assertDontSee('btn btn-sm btn-primary ms-2 active', false);
    }

    public function test_sign_up_button_is_active_on_register_page(): void
    {
        $this->get(route('shop.register'))
            ->assertOk()
            ->assertSee('<a class="btn btn-sm btn-primary ms-2 active" href="' . route('shop.register') . '">Sign up</a>', false)
            ->assertSee('<a class="nav-link " href="' . route('shop.login') . '">Login</a>', false);
    }

    public function test_my_orders_item_is_active_on_orders_page(): void
    {
        $this->actingAsCustomer();

        $this->get(route('shop.account.orders.index'))
            ->assertOk()
            ->assertSee('<a class="nav-link dropdown-toggle active" data-bs-toggle="dropdown" href="#">', false)
            ->assertSee('<a class="dropdown-item active" href="' . route('shop.account.orders.index') . '">My Orders</a>', false)
            ->assertSee('<a class="dropdown-item " href="' . route('shop.account.profile') . '">My Profile</a>', false);
    }

    public function test_custom_creations_link_is_active_on_gallery_pages(): void
    {
        $this->get(route('shop.custom-creations.index'))
            ->assertOk()
            ->assertSee('<a class="nav-link active" href="' . route('shop.custom-creations.index') . '"><i class="bi bi-stars me-1"></i>Custom Creations</a>', false)
            ->assertSee('<a class="nav-link dropdown-toggle " href="#" data-bs-toggle="dropdown">More</a>', false);
    }
}
