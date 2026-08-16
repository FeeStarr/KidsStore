<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\PasswordResetNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    /**
     * Show the "forgot password" form.
     */
    public function showForgotForm(): View
    {
        return view('admin.auth.forgot-password');
    }

    /**
     * Send password reset email.
     */
    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Always return the same message to prevent user enumeration
        $user = User::where('email', $request->email)->first();

        if ($user && $user->isAdmin()) {
            $token = Str::random(64);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                [
                    'token' => Hash::make($token),
                    'created_at' => now(),
                ]
            );

            $user->notify(new PasswordResetNotification($token));
        }

        return back()->with('status', 'If an account exists with that email, a password reset link has been sent.');
    }

    /**
     * Show the password reset form.
     */
    public function showResetForm(Request $request): View|RedirectResponse
    {
        $token = $request->token;
        $email = $request->email;

        // Verify token exists and is not expired (60 minutes)
        $reset = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->where('created_at', '>', now()->subMinutes(60))
            ->first();

        if (!$reset || !Hash::check($token, $reset->token)) {
            return redirect()->route('admin.login')
                ->withErrors(['email' => 'This password reset link is invalid or has expired.']);
        }

        return view('admin.auth.reset-password', [
            'token' => $token,
            'email' => $email,
        ]);
    }

    /**
     * Reset the admin's password.
     */
    public function resetPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'token' => ['required'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        // Verify token is valid
        $reset = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('created_at', '>', now()->subMinutes(60))
            ->first();

        if (!$reset || !Hash::check($request->token, $reset->token)) {
            return redirect()->route('admin.login')
                ->withErrors(['email' => 'This password reset link is invalid or has expired.']);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !$user->isAdmin()) {
            return back()->withErrors(['email' => 'User not found.']);
        }

        // Update password
        $user->update(['password' => Hash::make($request->password)]);

        // Delete used token
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return redirect()->route('admin.login')
            ->with('status', 'Your password has been reset successfully. Please log in.');
    }
}
