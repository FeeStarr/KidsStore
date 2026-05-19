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

class AuthController extends Controller
{
    public function showLogin(): RedirectResponse|View
    {
        if (auth()->check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        Log::info('Login attempt', ['email' => $request->email]);

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $credentials['email'])->first();
        if (! $user) {
            Log::info('User not found', ['email' => $credentials['email']]);
            return back()->withErrors([
                'email' => 'User not found.',
            ])->onlyInput('email');
        }

        if (! $user->isAdmin()) {
            Log::info('User not admin', ['email' => $credentials['email'], 'role' => $user->role]);
            return back()->withErrors([
                'email' => 'You do not have admin access.',
            ])->onlyInput('email');
        }

        if (! Hash::check($credentials['password'], $user->password)) {
            Log::info('Invalid password', ['email' => $credentials['email']]);
            return back()->withErrors([
                'email' => 'Invalid password.',
            ])->onlyInput('email');
        }

        $user->generateTwoFactorCode();
        $user->notify(new AdminTwoFactorCodeNotification($user->two_factor_code));

        $request->session()->put('admin_2fa_user_id', $user->id);
        $request->session()->put('admin_2fa_remember', $request->boolean('remember'));

        return redirect()->route('admin.login.2fa')
            ->with('success', 'A verification code has been sent to your email.');
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
        $user = User::find($userId);

        if (! $user) {
            return redirect()->route('admin.login')->withErrors([
                'email' => 'Please sign in again.',
            ]);
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        if (! $user->validateTwoFactorCode($data['code'])) {
            return back()->withErrors([
                'code' => 'Invalid or expired verification code.',
            ]);
        }

        Auth::login($user, (bool) $request->session()->pull('admin_2fa_remember', false));
        $user->resetTwoFactorCode();

        $request->session()->forget('admin_2fa_user_id');
        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}