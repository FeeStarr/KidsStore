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

        // Session timeout: 8 hours
        $loginAt = session('portal_login_at', 0);
        if ($loginAt && now()->timestamp - $loginAt > 28800) {
            $request->session()->forget(['portal_station_id', 'portal_station_name', 'portal_login_at']);
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['error' => 'Session expired. Please log in again.'], 401);
            }
            return redirect()->route('pickup-portal.login')
                ->with('error', 'Session expired. Please log in again.');
        }

        return $next($request);
    }
}
