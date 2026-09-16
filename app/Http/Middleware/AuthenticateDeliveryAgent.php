<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticateDeliveryAgent
{
    public function handle(Request $request, Closure $next)
    {
        if (! Auth::guard('delivery')->check()) {
            return redirect()->route('delivery-portal.login');
        }

        $user = Auth::guard('delivery')->user();

        if ($user->role !== User::ROLE_DELIVERY_AGENT) {
            abort(403);
        }

        if (! $user->is_active) {
            Auth::guard('delivery')->logout();
            return redirect()->route('delivery-portal.login')
                ->withErrors(['email' => 'Your account has been deactivated.']);
        }

        $agent = $user->deliveryAgent;
        if (! $agent || ! $agent->is_active) {
            Auth::guard('delivery')->logout();
            return redirect()->route('delivery-portal.login')
                ->withErrors(['email' => 'Your delivery agent account is inactive.']);
        }

        if ($user->must_change_password) {
            $allowedRoutes = ['delivery-portal.profile', 'delivery-portal.profile.password', 'delivery-portal.logout'];
            if (! in_array($request->route()->getName(), $allowedRoutes)) {
                return redirect()->route('delivery-portal.profile')
                    ->with('warning', 'You must change your password before continuing.');
            }
        }

        // Set user resolver so downstream middleware resolve correctly
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
