<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\AdminTwoFactorCodeNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function showLogin(): RedirectResponse|View
    {
        if (auth()->guard('admin')->check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Brute-force protection - max 5 attempts per email+IP per minute
        $throttleKey = 'admin-login:' . Str::lower($credentials['email']) . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return back()->withErrors([
                'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ])->onlyInput('email');
        }

        $user = User::where('email', $credentials['email'])->first();

        // Use a single generic error for wrong credentials to prevent user enumeration
        $genericError = 'Invalid credentials.';

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($throttleKey, 60);
            Log::warning('Admin login failed', ['email' => $credentials['email'], 'ip' => $request->ip()]);
            return back()->withErrors(['email' => $genericError])->onlyInput('email');
        }

        if (! $user->isAdmin()) {
            RateLimiter::hit($throttleKey, 60);
            Log::warning('Non-admin login attempt on admin panel', ['email' => $credentials['email']]);
            return back()->withErrors(['email' => $genericError])->onlyInput('email');
        }

        if (! $user->is_active) {
            RateLimiter::hit($throttleKey, 60);
            return back()->withErrors(['email' => $genericError])->onlyInput('email');
        }

        RateLimiter::clear($throttleKey);

        // 2FA: required when explicitly enabled, OR always required for superadmin
        $requires2FA = $user->two_factor_enabled || $user->role === 'superadmin';

        if ($requires2FA) {
            $user->generateTwoFactorCode();

            try {
                $user->notify(new AdminTwoFactorCodeNotification($user->two_factor_code));
            } catch (\Throwable $e) {
                Log::error('Failed to send 2FA email', [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);
            }

            $request->session()->put('admin_2fa_user_id', $user->id);
            $request->session()->put('admin_2fa_remember', $request->boolean('remember'));

            return redirect()->route('admin.login.2fa')
                ->with('success', 'A verification code has been sent to your email.');
        }

        // 2FA disabled - log in directly
        Auth::guard('admin')->login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->route('admin.dashboard');
    }

    public function showTwoFactorForm(Request $request): RedirectResponse|View
    {
        if (! $request->session()->has('admin_2fa_user_id')) {
            return redirect()->route('admin.login');
        }

        return view('admin.auth.two-factor');
    }

    public function verifyTwoFactor(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('admin_2fa_user_id');
        $user   = User::find($userId);

        if (! $user) {
            return redirect()->route('admin.login')->withErrors([
                'email' => 'Please sign in again.',
            ]);
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'max:20'],
        ]);

        $input = trim($data['code']);

        // Accept a backup code as an alternative to the emailed OTP
        if ($user->useBackupCode($input)) {
            Auth::guard('admin')->login($user, (bool) $request->session()->pull('admin_2fa_remember', false));
            $request->session()->forget('admin_2fa_user_id');
            $request->session()->regenerate();
            return redirect()->route('admin.dashboard')
                ->with('warning', 'Signed in using a backup code. Please contact your administrator to set up a new one.');
        }

        if (! $user->validateTwoFactorCode($input)) {
            return back()->withErrors([
                'code' => 'Invalid or expired verification code.',
            ]);
        }

        Auth::guard('admin')->login($user, (bool) $request->session()->pull('admin_2fa_remember', false));
        $user->resetTwoFactorCode();

        $request->session()->forget('admin_2fa_user_id');
        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();

        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}