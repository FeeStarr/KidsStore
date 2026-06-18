<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\AdminTwoFactorCodeNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('shop.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Brute-force protection — 5 attempts per email+IP per minute
        $throttleKey = 'shop-login:' . Str::lower($credentials['email']) . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return back()->withErrors([
                'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ])->onlyInput('email');
        }

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! $user->isCustomer() || ! Auth::validate($credentials)) {
            RateLimiter::hit($throttleKey, 60);
            return back()
                ->withErrors(['email' => 'Invalid email or password.'])
                ->onlyInput('email');
        }

        if (! $user->is_active) {
            RateLimiter::hit($throttleKey, 60);
            return back()->withErrors(['email' => 'Your account has been deactivated.'])->onlyInput('email');
        }

        RateLimiter::clear($throttleKey);

        // 2FA required for this user?
        if ($user->two_factor_enabled) {
            $user->generateTwoFactorCode();
            $user->notify(new AdminTwoFactorCodeNotification($user->two_factor_code));

            $request->session()->put('shop_2fa_user_id',  $user->id);
            $request->session()->put('shop_2fa_remember', $request->boolean('remember'));

            return redirect()->route('shop.2fa.show')
                ->with('status', 'A verification code has been sent to your email.');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('shop.home'));
    }

    public function show2FA(Request $request)
    {
        if (! $request->session()->has('shop_2fa_user_id')) {
            return redirect()->route('shop.login');
        }
        return view('shop.auth.two-factor');
    }

    public function verify2FA(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('shop_2fa_user_id');
        $user   = User::find($userId);

        if (! $user) {
            return redirect()->route('shop.login')
                ->withErrors(['email' => 'Session expired. Please sign in again.']);
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'max:20'],
        ]);

        $input = trim($data['code']);

        // Accept backup code as an alternative
        if ($user->useBackupCode($input)) {
            Auth::login($user, (bool) $request->session()->pull('shop_2fa_remember', false));
            $request->session()->forget('shop_2fa_user_id');
            $request->session()->regenerate();
            return redirect()->intended(route('shop.home'))
                ->with('warning', 'Signed in using a backup code. Your code has been used and is no longer valid.');
        }

        if (! $user->validateTwoFactorCode($input)) {
            return back()->withErrors(['code' => 'Invalid or expired verification code.']);
        }

        Auth::login($user, (bool) $request->session()->pull('shop_2fa_remember', false));
        $user->resetTwoFactorCode();
        $request->session()->forget('shop_2fa_user_id');
        $request->session()->regenerate();

        return redirect()->intended(route('shop.home'));
    }

    public function showRegister()
    {
        return view('shop.auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:120'],
            'email'    => ['required', 'email', Rule::unique('users', 'email')],
            'phone'    => ['nullable', 'string', 'max:30'],
            'address'  => ['nullable', 'string', 'max:500'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $data['role'] = User::ROLE_CUSTOMER;

        $user = User::create($data);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('shop.home')->with('success', 'Welcome, '.$user->name.'!');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('shop.home');
    }
}
