<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticateAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (config('app.env') === 'production' && config('app.debug') === true) {
            abort(500, 'APP_DEBUG must be false in production.');
        }

        if (! Auth::guard('admin')->check()) {
            return redirect()->route('admin.login');
        }

        $user = Auth::guard('admin')->user();

        if (! $user->isAdmin() && ! $user->isStaff()) {
            abort(403, 'Access denied.');
        }

        if (! $user->is_active) {
            Auth::guard('admin')->logout();
            return redirect()->route('admin.login')
                ->withErrors(['email' => 'Your account has been deactivated.']);
        }

        // Set user resolver so downstream middleware (LogUserActivity, EnsurePermission) resolve correctly
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
