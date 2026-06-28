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

        if (!$user || !$user->isAdmin()) {
            return redirect()->route('admin.login');
        }

        return $next($request);
    }
}
