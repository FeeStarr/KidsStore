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
        if (! Auth::check()) {
            return redirect()->route('delivery-portal.login');
        }

        $user = Auth::user();

        // Must be delivery agent role
        if ($user->role !== User::ROLE_DELIVERY_AGENT) {
            abort(403);
        }

        // User must be active
        if (! $user->is_active) {
            Auth::logout();
            return redirect()->route('delivery-portal.login')
                ->withErrors(['email' => 'Your account has been deactivated.']);
        }

        // Must have linked delivery agent that is active
        $agent = $user->deliveryAgent;
        if (! $agent || ! $agent->is_active) {
            Auth::logout();
            return redirect()->route('delivery-portal.login')
                ->withErrors(['email' => 'Your delivery agent account is inactive.']);
        }

        // Force password change — only allow profile/password/logout
        if ($user->must_change_password) {
            $allowedRoutes = ['delivery-portal.profile', 'delivery-portal.profile.password', 'delivery-portal.logout'];
            if (! in_array($request->route()->getName(), $allowedRoutes)) {
                return redirect()->route('delivery-portal.profile')
                    ->with('warning', 'You must change your password before continuing.');
            }
        }

        return $next($request);
    }
}
