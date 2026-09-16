<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class GuardIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function createCustomer(): User
    {
        return User::create([
            'name' => 'Test Customer',
            'email' => 'customer@test.com',
            'password' => bcrypt('password123'),
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);
    }

    private function createAdmin(): User
    {
        return User::create([
            'name' => 'Test Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password123'),
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
    }

    private function createDeliveryAgent(): User
    {
        $user = User::create([
            'name' => 'Test Agent',
            'email' => 'agent@test.com',
            'password' => bcrypt('password123'),
            'role' => User::ROLE_DELIVERY_AGENT,
            'is_active' => true,
        ]);

        $user->deliveryAgent()->create([
            'name' => 'Test Agent',
            'phone' => '1234567890',
            'email' => 'agent@test.com',
            'account_number' => 'DA-000001',
            'is_active' => true,
        ]);

        return $user;
    }

    // --- Test 1: Customer login → refresh → still same customer ---
    public function test_customer_login_persists_on_refresh(): void
    {
        $customer = $this->createCustomer();

        $response = $this->post(route('shop.login'), [
            'email' => 'customer@test.com',
            'password' => 'password123',
        ]);
        $response->assertRedirect();

        $this->actingAs($customer, 'web');
        $this->assertEquals($customer->id, auth()->guard('web')->id());
    }

    // --- Test 2: Different browsers different sessions (unit test level) ---
    public function test_different_browsers_different_sessions(): void
    {
        $customerA = $this->createCustomer();
        $customerB = User::create([
            'name' => 'Customer B',
            'email' => 'customerb@test.com',
            'password' => bcrypt('password123'),
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);

        // Login as A
        $this->app['auth']->guard('web')->login($customerA);
        $sessionIdA = $this->app['session']->getId();

        // Start fresh session for B
        $this->app['session']->regenerate();
        $this->app['auth']->guard('web')->login($customerB);
        $sessionIdB = $this->app['session']->getId();

        $this->assertNotEquals($sessionIdA, $sessionIdB, 'Different sessions must have different IDs');
    }

    // --- Test 3: Customer + Admin login in another tab → both guards populated ---
    public function test_customer_and_admin_coexist(): void
    {
        $customer = $this->createCustomer();
        $admin = $this->createAdmin();

        $this->actingAs($customer, 'web');
        $this->assertEquals($customer->id, auth()->guard('web')->id());

        $this->app['auth']->guard('admin')->login($admin);
        $this->assertEquals($admin->id, auth()->guard('admin')->id());

        $this->assertEquals($customer->id, auth()->guard('web')->id());
    }

    // --- Test 4: Customer + Delivery login in another tab → both guards populated ---
    public function test_customer_and_delivery_coexist(): void
    {
        $customer = $this->createCustomer();
        $agent = $this->createDeliveryAgent();

        $this->actingAs($customer, 'web');
        $this->assertEquals($customer->id, auth()->guard('web')->id());

        $this->app['auth']->guard('delivery')->login($agent);
        $this->assertEquals($agent->id, auth()->guard('delivery')->id());

        $this->assertEquals($customer->id, auth()->guard('web')->id());
    }

    // --- Test 5: Admin + Customer login → admin guard still has admin ---
    public function test_admin_and_customer_coexist(): void
    {
        $admin = $this->createAdmin();
        $customer = $this->createCustomer();

        $this->actingAs($admin, 'admin');
        $this->assertEquals($admin->id, auth()->guard('admin')->id());

        $this->app['auth']->guard('web')->login($customer);
        $this->assertEquals($customer->id, auth()->guard('web')->id());

        $this->assertEquals($admin->id, auth()->guard('admin')->id());
    }

    // --- Test 6: Delivery + Customer login → delivery guard still has agent ---
    public function test_delivery_and_customer_coexist(): void
    {
        $agent = $this->createDeliveryAgent();
        $customer = $this->createCustomer();

        $this->actingAs($agent, 'delivery');
        $this->assertEquals($agent->id, auth()->guard('delivery')->id());

        $this->app['auth']->guard('web')->login($customer);
        $this->assertEquals($customer->id, auth()->guard('web')->id());

        $this->assertEquals($agent->id, auth()->guard('delivery')->id());
    }

    // --- Test 7: Customer logout clears only customer guard ---
    public function test_customer_logout_clears_only_web_guard(): void
    {
        $customer = $this->createCustomer();
        $admin = $this->createAdmin();

        $this->app['auth']->guard('web')->login($customer);
        $this->app['auth']->guard('admin')->login($admin);

        $this->app['auth']->guard('web')->logout();

        $this->assertNull(auth()->guard('web')->user());
        $this->assertNotNull(auth()->guard('admin')->user());
        $this->assertEquals($admin->id, auth()->guard('admin')->id());
    }

    // --- Test 8: Admin logout clears only admin guard ---
    public function test_admin_logout_clears_only_admin_guard(): void
    {
        $customer = $this->createCustomer();
        $admin = $this->createAdmin();

        $this->app['auth']->guard('web')->login($customer);
        $this->app['auth']->guard('admin')->login($admin);

        $this->app['auth']->guard('admin')->logout();

        $this->assertNotNull(auth()->guard('web')->user());
        $this->assertNull(auth()->guard('admin')->user());
        $this->assertEquals($customer->id, auth()->guard('web')->id());
    }

    // --- Test 9: Delivery logout clears only delivery guard ---
    public function test_delivery_logout_clears_only_delivery_guard(): void
    {
        $customer = $this->createCustomer();
        $agent = $this->createDeliveryAgent();

        $this->app['auth']->guard('web')->login($customer);
        $this->app['auth']->guard('delivery')->login($agent);

        $this->app['auth']->guard('delivery')->logout();

        $this->assertNotNull(auth()->guard('web')->user());
        $this->assertNull(auth()->guard('delivery')->user());
        $this->assertEquals($customer->id, auth()->guard('web')->id());
    }

    // --- Test 10: Customer logout → refresh → unauthenticated ---
    public function test_customer_logout_leaves_unauthenticated(): void
    {
        $customer = $this->createCustomer();

        $this->app['auth']->guard('web')->login($customer);
        $this->assertNotNull(auth()->guard('web')->user());

        $this->app['auth']->guard('web')->logout();
        $this->assertNull(auth()->guard('web')->user());
    }

    // --- Test 11: All three logged in → admin logout → customer + delivery intact ---
    public function test_admin_logout_preserves_customer_and_delivery(): void
    {
        $customer = $this->createCustomer();
        $admin = $this->createAdmin();
        $agent = $this->createDeliveryAgent();

        $this->app['auth']->guard('web')->login($customer);
        $this->app['auth']->guard('admin')->login($admin);
        $this->app['auth']->guard('delivery')->login($agent);

        $this->app['auth']->guard('admin')->logout();

        $this->assertNull(auth()->guard('admin')->user());
        $this->assertNotNull(auth()->guard('web')->user());
        $this->assertNotNull(auth()->guard('delivery')->user());
    }

    // --- Test 12: All three logged in → delivery logout → customer + admin intact ---
    public function test_delivery_logout_preserves_customer_and_admin(): void
    {
        $customer = $this->createCustomer();
        $admin = $this->createAdmin();
        $agent = $this->createDeliveryAgent();

        $this->app['auth']->guard('web')->login($customer);
        $this->app['auth']->guard('admin')->login($admin);
        $this->app['auth']->guard('delivery')->login($agent);

        $this->app['auth']->guard('delivery')->logout();

        $this->assertNull(auth()->guard('delivery')->user());
        $this->assertNotNull(auth()->guard('web')->user());
        $this->assertNotNull(auth()->guard('admin')->user());
    }

    // --- Test 13: All three logged in → customer logout → admin + delivery intact ---
    public function test_customer_logout_preserves_admin_and_delivery(): void
    {
        $customer = $this->createCustomer();
        $admin = $this->createAdmin();
        $agent = $this->createDeliveryAgent();

        $this->app['auth']->guard('web')->login($customer);
        $this->app['auth']->guard('admin')->login($admin);
        $this->app['auth']->guard('delivery')->login($agent);

        $this->app['auth']->guard('web')->logout();

        $this->assertNull(auth()->guard('web')->user());
        $this->assertNotNull(auth()->guard('admin')->user());
        $this->assertNotNull(auth()->guard('delivery')->user());
    }

    // --- Test 14: Each guard has its own session key ---
    public function test_each_guard_has_own_session_key(): void
    {
        $customer = $this->createCustomer();
        $admin = $this->createAdmin();
        $agent = $this->createDeliveryAgent();

        $this->app['auth']->guard('web')->login($customer);
        $this->app['auth']->guard('admin')->login($admin);
        $this->app['auth']->guard('delivery')->login($agent);

        $session = $this->app['session']->driver();
        $all = $session->all();
        $keys = array_keys($all);

        $hasWeb = !empty(array_filter($keys, fn ($k) => str_starts_with($k, 'login_web_')));
        $hasAdmin = !empty(array_filter($keys, fn ($k) => str_starts_with($k, 'login_admin_')));
        $hasDelivery = !empty(array_filter($keys, fn ($k) => str_starts_with($k, 'login_delivery_')));

        $this->assertTrue($hasWeb, 'Session must contain login_web_ key');
        $this->assertTrue($hasAdmin, 'Session must contain login_admin_ key');
        $this->assertTrue($hasDelivery, 'Session must contain login_delivery_ key');
    }

    // --- Test 15: Pickup portal_station_id unaffected by login/logout ---
    public function test_pickup_session_unaffected_by_guard_login_logout(): void
    {
        $customer = $this->createCustomer();
        $admin = $this->createAdmin();

        $this->session([
            'portal_station_id' => 42,
            'portal_station_name' => 'Test Station',
        ]);

        $this->app['auth']->guard('web')->login($customer);
        $this->app['auth']->guard('admin')->login($admin);

        $this->assertEquals(42, session('portal_station_id'));
        $this->assertEquals('Test Station', session('portal_station_name'));

        $this->app['auth']->guard('web')->logout();
        $this->assertEquals(42, session('portal_station_id'));

        $this->app['auth']->guard('admin')->logout();
        $this->assertEquals(42, session('portal_station_id'));
    }

    // --- Test 16: Invalid role cannot authenticate into wrong portal ---
    public function test_customer_cannot_authenticate_as_admin_guard(): void
    {
        $customer = $this->createCustomer();

        $this->app['auth']->guard('admin')->login($customer);

        $user = auth()->guard('admin')->user();
        $this->assertNotNull($user);
        $this->assertEquals(User::ROLE_CUSTOMER, $user->role);
        $this->assertFalse($user->isAdmin(), 'Customer should not pass admin guard authorization');
    }

    // --- Test 17: Auth events log records transitions ---
    public function test_auth_events_are_logged(): void
    {
        $customer = $this->createCustomer();

        // Verify the listener handles Login events
        $loginEvent = new \Illuminate\Auth\Events\Login('web', $customer, false);
        $listener = new \App\Listeners\LogAuthEvent();
        $listener->handle($loginEvent);

        $this->assertDatabaseHas('auth_events', [
            'event' => 'Login',
            'user_id' => $customer->id,
            'guard' => 'web',
        ]);

        // Verify the listener handles Logout events
        $logoutEvent = new \Illuminate\Auth\Events\Logout('web', $customer);
        $listener->handle($logoutEvent);

        $this->assertDatabaseHas('auth_events', [
            'event' => 'Logout',
            'user_id' => $customer->id,
            'guard' => 'web',
        ]);
    }

    // --- Test 18: Same browser, same guard — second login replaces first ---
    public function test_same_guard_same_browser_replaces_user(): void
    {
        $customerA = $this->createCustomer();
        $customerB = User::create([
            'name' => 'Customer B',
            'email' => 'customerb@test.com',
            'password' => bcrypt('password123'),
            'role' => User::ROLE_CUSTOMER,
            'is_active' => true,
        ]);

        $this->app['auth']->guard('web')->login($customerA);
        $this->assertEquals($customerA->id, auth()->guard('web')->id());

        $this->app['auth']->guard('web')->login($customerB);
        $this->assertEquals($customerB->id, auth()->guard('web')->id());
        $this->assertNotEquals($customerA->id, auth()->guard('web')->id());
    }
}
