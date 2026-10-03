<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\AdminTwoFactorCodeNotification;
use App\Notifications\WelcomeNotification;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            Log::warning('Google sign-in failed', ['error' => $e->getMessage()]);

            return redirect()->route('shop.login')
                ->withErrors(['email' => 'Google sign-in failed or was cancelled. Please try again.']);
        }

        $email = $googleUser->getEmail();

        if (! $email) {
            return redirect()->route('shop.login')
                ->withErrors(['email' => 'Google did not share an email address. Please sign up with a password.']);
        }

        $user = User::where('google_id', $googleUser->getId())->first()
            ?? User::where('email', $email)->first();

        if ($user) {
            // Mirror password login: only active customers may sign in this way.
            if (! $user->isCustomer() || ! $user->is_active) {
                return redirect()->route('shop.login')
                    ->withErrors(['email' => 'Invalid email or password.']);
            }

            $user->google_id = $googleUser->getId();
            // Google verified this address on our behalf.
            $user->email_verified_at = $user->email_verified_at ?? now();
            $user->save();
        } else {
            $user = new User([
                'name'     => $googleUser->getName() ?: ($googleUser->getNickname() ?: 'Google User'),
                'email'    => $email,
                'password' => Str::random(32),
                'role'     => User::ROLE_CUSTOMER,
            ]);
            $user->google_id = $googleUser->getId();
            $user->email_verified_at = now();
            $user->save();

            $user->notify(new WelcomeNotification);
        }

        // 2FA honored exactly like password login (AuthController::login).
        if ($user->two_factor_enabled) {
            $user->generateTwoFactorCode();

            try {
                $user->notify(new AdminTwoFactorCodeNotification($user->two_factor_code));
            } catch (\Throwable $e) {
                Log::error('Failed to send shop 2FA email', [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);
            }

            $request->session()->put('shop_2fa_user_id', $user->id);
            $request->session()->put('shop_2fa_remember', false);

            return redirect()->route('shop.2fa.show')
                ->with('status', 'A verification code has been sent to your email.');
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        app(CartService::class)->mergeSessionIntoDatabase();

        return redirect()->intended(route('shop.home'))
            ->with('success', 'Welcome back, ' . $user->name . '!');
    }
}
