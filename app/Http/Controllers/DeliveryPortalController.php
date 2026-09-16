<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use App\Services\DeliveryAgentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Contracts\View\View;

class DeliveryPortalController extends Controller
{
    public function __construct(
        private DeliveryAgentService $service
    ) {
    }

    public function showLogin(): View
    {
        return view('delivery-portal.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = 'delivery-portal-login:' . $request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return back()->withErrors(['email' => "Too many attempts. Try again in {$seconds} seconds."])
                ->withInput();
        }

        if (! Auth::attempt(['email' => $data['email'], 'password' => $data['password']])) {
            RateLimiter::hit($throttleKey, 60);
            return back()->withErrors(['email' => 'Invalid credentials.'])
                ->withInput();
        }

        RateLimiter::clear($throttleKey);

        $user = Auth::user();

        // Validate role and active status
        if ($user->role !== User::ROLE_DELIVERY_AGENT || ! $user->is_active) {
            Auth::logout();
            return back()->withErrors(['email' => 'Invalid credentials.'])
                ->withInput();
        }

        if (! $user->deliveryAgent || ! $user->deliveryAgent->is_active) {
            Auth::logout();
            return back()->withErrors(['email' => 'Your account is inactive.'])
                ->withInput();
        }

        $request->session()->regenerate();

        if ($user->must_change_password) {
            return redirect()->route('delivery-portal.profile')
                ->with('warning', 'You must change your temporary password before continuing.');
        }

        return redirect()->route('delivery-portal.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('delivery-portal.login');
    }

    public function dashboard(): View
    {
        $agent = Auth::user()->deliveryAgent;
        $stats = $this->service->getDashboardStats($agent->id);

        return view('delivery-portal.dashboard', compact('agent', 'stats'));
    }

    public function deliveries(Request $request): View
    {
        $agent  = Auth::user()->deliveryAgent;
        $filter = $request->input('filter', 'assigned');
        $deliveries = $this->service->getDeliveries($agent->id, $filter);

        return view('delivery-portal.deliveries', compact('agent', 'deliveries', 'filter'));
    }

    public function show(Order $order): View
    {
        $agent = Auth::user()->deliveryAgent;

        abort_unless((int) $order->delivery_agent_id === (int) $agent->id, 403);
        abort_unless(in_array($order->delivery_status, [
            'assigned', 'received', 'out_for_delivery', 'delivered', 'failed',
        ]), 404);

        $order->load('customer', 'deliveryLocation', 'items.product', 'items.variant');

        return view('delivery-portal.delivery-show', compact('agent', 'order'));
    }

    public function markReceived(Order $order): RedirectResponse
    {
        $agent = Auth::user()->deliveryAgent;
        $this->service->markReceived($order, $agent);

        return back()->with('success', 'Parcel marked as received.');
    }

    public function markOutForDelivery(Order $order): RedirectResponse
    {
        $agent = Auth::user()->deliveryAgent;
        $this->service->markOutForDelivery($order, $agent);

        return back()->with('success', 'Order marked as out for delivery. Customer has been notified.');
    }

    public function markDelivered(Order $order): RedirectResponse
    {
        $agent = Auth::user()->deliveryAgent;
        $this->service->markDelivered($order, $agent);

        return back()->with('success', 'Order marked as delivered.');
    }

    public function reportIssue(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'in:customer_unavailable,customer_unreachable,wrong_address,customer_refused,damaged_parcel,other'],
            'notes'  => ['nullable', 'string', 'max:500'],
        ]);

        $agent = Auth::user()->deliveryAgent;
        $this->service->reportIssue($order, $agent, $data['reason'], $data['notes']);

        return back()->with('success', 'Delivery issue reported.');
    }

    public function profile(): View
    {
        $agent = Auth::user()->deliveryAgent;

        return view('delivery-portal.profile', compact('agent'));
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        Auth::user()->update([
            'password'             => Hash::make($request->password),
            'must_change_password' => false,
        ]);

        return back()->with('success', 'Password updated successfully.');
    }
}
