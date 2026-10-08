<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class PortalGuestRedirectTest extends TestCase
{
    public function test_authenticated_delivery_user_visiting_portal_root_redirects_to_dashboard(): void
    {
        $agent = $this->makeCustomer(['role' => User::ROLE_DELIVERY_AGENT]);

        $this->actingAs($agent, 'delivery')
            ->get('/delivery-portal')
            ->assertRedirect(route('delivery-portal.dashboard'));
    }

    public function test_guest_visiting_portal_root_sees_login_form(): void
    {
        $this->get('/delivery-portal')
            ->assertOk()
            ->assertSee('Delivery Agent Login');
    }

    public function test_authenticated_customer_visiting_portal_root_still_sees_login_form(): void
    {
        $this->actingAsCustomer();

        $this->get('/delivery-portal')
            ->assertOk()
            ->assertSee('Delivery Agent Login');
    }

    public function test_authenticated_admin_visiting_admin_login_redirects_to_dashboard(): void
    {
        $admin = $this->makeCustomer(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin, 'admin')
            ->get('/admin/login')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_authenticated_customer_visiting_shop_login_redirects_to_home(): void
    {
        $this->actingAsCustomer();

        $this->get(route('shop.login'))
            ->assertRedirect('/');
    }
}
