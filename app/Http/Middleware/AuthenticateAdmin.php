<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticateAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (! Auth::check()) {
            return redirect()->route('admin.login');
        }

        $user = Auth::user();

        // Must still be an admin/staff role
        if (! $user->isAdmin() && ! $user->isStaff()) {
            Auth::logout();
            return redirect()->route('admin.login')
                ->withErrors(['email' => 'Access denied.']);
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
