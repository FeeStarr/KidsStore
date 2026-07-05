<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AuthenticatePickupPortal
{
    public function handle(Request $request, Closure $next)
    {
        if (! session('portal_station_id')) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Not authenticated.'], 401);
            }
            return redirect()->route('pickup-portal.login');
        }

        return $next($request);
    }
}
