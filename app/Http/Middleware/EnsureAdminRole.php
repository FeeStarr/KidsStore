<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureAdminRole
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('admin.login');
        }

        // Authenticated but not an admin -> forbidden
        if (! $user->isAdmin()) {
            abort(403, 'Access denied.');
        }

        return $next($request);
    }
}
