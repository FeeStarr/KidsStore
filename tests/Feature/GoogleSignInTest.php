<?php

namespace Tests\Feature;

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleSignInTest extends TestCase
{
    private function withGoogleConfig(): void
    {
        config([
            'services.google.client_id'     => 'test-client-id.apps.googleusercontent.com',
            'services.google.client_secret' => 'test-secret',
            'services.google.redirect'      => 'http://localhost:8000/auth/google/callback',
        ]);
    }

    private function fakeGoogleUser(array $attributes = []): SocialiteUser
    {
        return (new SocialiteUser)->map(array_merge([
            'id'      => 'google-uid-123',
            'name'    => 'Jane Google',
            'email'   => 'jane.google@example.com',
            'avatar'  => 'https://example.com/avatar.png',
        ], $attributes));
    }

    private function fakeProvider(?SocialiteUser $user = null, ?\Throwable $exception = null): void
    {
        $provider = Mockery::mock();

        if ($exception) {
            $provider->shouldReceive('user')->andThrow($exception);
        } else {
            $provider->shouldReceive('user')->andReturn($user);
        }

        $provider->shouldReceive('redirect')
            ->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_google_button_is_visible_on_login_and_register_when_configured(): void
    {
        $this->withGoogleConfig();

        $this->get(route('shop.login'))
            ->assertOk()
            ->assertSee('Sign in with Google')
            ->assertSee('href="' . route('shop.login.google') . '"', false);

        $this->get(route('shop.register'))
            ->assertOk()
            ->assertSee('Sign up with Google')
            ->assertSee('href="' . route('shop.login.google') . '"', false);
    }

    public function test_google_button_is_hidden_without_client_id(): void
    {
        config(['services.google.client_id' => null]);

        $this->get(route('shop.login'))
            ->assertOk()
            ->assertDontSee('Sign in with Google');

        $this->get(route('shop.register'))
            ->assertOk()
            ->assertDontSee('Sign up with Google');
    }

    public function test_redirect_route_sends_user_to_google(): void
    {
        $this->fakeProvider();

        $this->get(route('shop.login.google'))
            ->assertRedirect('https://accounts.google.com/o/oauth2/auth');
    }

    public function test_new_google_user_is_created_and_logged_in(): void
    {
        $this->fakeProvider($this->fakeGoogleUser());

        $this->get(route('shop.login.google.callback'))
            ->assertRedirect(route('shop.home'));

        $this->assertAuthenticated();

        $user = User::where('email', 'jane.google@example.com')->firstOrFail();
        $this->assertSame(User::ROLE_CUSTOMER, $user->role);
        $this->assertSame('google-uid-123', $user->google_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->password);
    }

    public function test_existing_customer_is_linked_and_logged_in(): void
    {
        $customer = $this->makeCustomer(['email' => 'jane.google@example.com']);
        $this->fakeProvider($this->fakeGoogleUser(['id' => 'google-uid-999']));

        $this->get(route('shop.login.google.callback'))
            ->assertRedirect(route('shop.home'));

        $this->assertAuthenticatedAs($customer);

        $this->assertSame(1, User::where('email', 'jane.google@example.com')->count());
        $this->assertSame('google-uid-999', $customer->fresh()->google_id);
    }

    public function test_admin_email_is_rejected(): void
    {
        $admin = $this->makeCustomer([
            'email' => 'boss@example.com',
            'role'  => User::ROLE_ADMIN,
        ]);
        $this->fakeProvider($this->fakeGoogleUser(['email' => 'boss@example.com']));

        $this->get(route('shop.login.google.callback'))
            ->assertRedirect(route('shop.login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertNull($admin->fresh()->google_id);
    }

    public function test_inactive_customer_is_rejected(): void
    {
        $customer = $this->makeCustomer([
            'email'     => 'banned@example.com',
            'is_active' => false,
        ]);
        $this->fakeProvider($this->fakeGoogleUser(['email' => 'banned@example.com']));

        $this->get(route('shop.login.google.callback'))
            ->assertRedirect(route('shop.login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertNull($customer->fresh()->google_id);
    }

    public function test_two_factor_user_is_sent_to_the_2fa_screen(): void
    {
        $customer = $this->makeCustomer([
            'email'             => 'twofactor@example.com',
            'two_factor_enabled' => true,
        ]);
        $this->fakeProvider($this->fakeGoogleUser(['email' => 'twofactor@example.com']));

        $this->get(route('shop.login.google.callback'))
            ->assertRedirect(route('shop.2fa.show'))
            ->assertSessionHas('shop_2fa_user_id', $customer->id);

        $this->assertGuest();
        $this->assertNotNull($customer->fresh()->two_factor_code);
    }

    public function test_callback_failure_returns_to_login_with_error(): void
    {
        $this->fakeProvider(null, new \RuntimeException('access_denied'));

        $this->get(route('shop.login.google.callback'))
            ->assertRedirect(route('shop.login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }
}
