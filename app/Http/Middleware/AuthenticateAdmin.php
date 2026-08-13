<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticateAdmin
{
    public function handle(Request $request, Closure $next)
    {
        // Prevent running admin panel with debug mode on in production
        if (config('app.env') === 'production' && config('app.debug') === true) {
            abort(500, 'APP_DEBUG must be false in production.');
        }

        if (! Auth::check()) {
            return redirect()->route('admin.login');
        }

        $user = Auth::user();

        // Must still be an admin/staff role
        if (! $user->isAdmin() && ! $user->isStaff()) {
            abort(403, 'Access denied.');
        }

        // Account must be active
        if (! $user->is_active) {
            Auth::logout();
            return redirect()->route('admin.login')
                ->withErrors(['email' => 'Your account has been deactivated.']);
        }

        return $next($request);
    }
}
