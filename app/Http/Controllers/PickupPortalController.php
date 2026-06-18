<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PickupStation;
use App\Services\OPayService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;

class PickupPortalController extends Controller
{
    public function __construct(private OrderService $orders, private OPayService $opay)
    {
    }

    /** Show login form */
    public function showLogin(): View
    {
        $stations = PickupStation::where('is_active', true)->orderBy('name')->get();
        return view('pickup-portal.login', compact('stations'));
    }

    /** Process PIN login */
    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pickup_station_id' => ['required', 'exists:pickup_stations,id'],
            'pin'               => ['required', 'string'],
        ]);

        // Max 5 PIN attempts per station+IP per minute
        $throttleKey = 'portal-login:' . $data['pickup_station_id'] . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return back()->withErrors(['pin' => "Too many attempts. Try again in {$seconds} seconds."])
                ->withInput();
        }

        $station = PickupStation::find($data['pickup_station_id']);

        if (! $station || ! $station->verifyPin($data['pin'])) {
            RateLimiter::hit($throttleKey, 60);
            return back()->withErrors(['pin' => 'Invalid PIN. Please try again.'])
                ->withInput();
        }

        RateLimiter::clear($throttleKey);

        session([
            'portal_station_id'   => $station->id,
            'portal_station_name' => $station->name,
        ]);

        return redirect()->route('pickup-portal.dashboard');
    }

    /** Logout */
    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget(['portal_station_id', 'portal_station_name']);
        return redirect()->route('pickup-portal.login');
    }

    /** Dashboard — orders for this station */
    public function dashboard(Request $request): View|RedirectResponse
    {
        if (! session('portal_station_id')) {
            return redirect()->route('pickup-portal.login');
        }

        $stationId  = (int) session('portal_station_id');
        $filter     = $request->input('filter', 'ready');

        $query = Order::with(['items.product', 'items.variant', 'customer'])
            ->where('pickup_station_id', $stationId)
            ->where('delivery_method', 'pickup');

        if ($filter === 'ready') {
            $query->where('status', 'ready for pick up');
        } else {
            $query->whereIn('status', ['ready for pick up', 'delivered', 'processing', 'confirmed']);
        }

        $orders     = $query->orderByDesc('order_date')->get();
        $readyCount = Order::where('pickup_station_id', $stationId)
            ->where('status', 'ready for pick up')
            ->count();

        return view('pickup-portal.dashboard', compact('orders', 'filter', 'readyCount'));
    }

    /**
     * Agent initiates OPay bank transfer for a customer paying at the station.
     * Returns JSON with the virtual account details.
     */
    public function initiatePayment(Request $request, Order $order): JsonResponse
    {
        if (! session('portal_station_id')) {
            return response()->json(['success' => false, 'message' => 'Not authenticated.'], 401);
        }

        if ((int) $order->pickup_station_id !== (int) session('portal_station_id')) {
            abort(403);
        }

        if ($order->payment_status === 'paid') {
            return response()->json(['success' => false, 'message' => 'Order is already paid.'], 400);
        }

        if ($order->status === 'cancelled') {
            return response()->json(['success' => false, 'message' => 'Order is cancelled.'], 400);
        }

        try {
            $transaction = $this->opay->initiate($order);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success'                => true,
            'reference'              => $transaction->reference,
            'virtual_account_number' => $transaction->virtual_account_number,
            'virtual_bank_name'      => $transaction->virtual_bank_name,
            'amount'                 => (float) $transaction->amount,
            'expires_at'             => $transaction->expires_at?->toISOString(),
            'seconds_remaining'      => $transaction->secondsRemaining(),
        ]);
    }

    /**
     * Agent polls OPay for payment status.
     */
    public function queryPayment(Request $request, Order $order): JsonResponse
    {
        if (! session('portal_station_id')) {
            return response()->json(['success' => false, 'message' => 'Not authenticated.'], 401);
        }

        if ((int) $order->pickup_station_id !== (int) session('portal_station_id')) {
            abort(403);
        }

        $transaction = $order->paymentTransactions()
            ->where('status', 'pending')
            ->latest()
            ->first();

        if (! $transaction) {
            return response()->json([
                'success'        => false,
                'payment_status' => $order->payment_status,
                'message'        => 'No active payment session.',
            ]);
        }

        // Throttle: minimum 15 s between queries
        if ($transaction->last_queried_at &&
            now()->diffInSeconds($transaction->last_queried_at) < 15) {
            return response()->json([
                'success'          => true,
                'status'           => $transaction->status,
                'payment_status'   => $order->fresh()->payment_status,
                'seconds_remaining'=> $transaction->secondsRemaining(),
                'throttled'        => true,
            ]);
        }

        $transaction = $this->opay->queryStatus($transaction);
        $order->refresh();

        return response()->json([
            'success'          => true,
            'status'           => $transaction->status,
            'payment_status'   => $order->payment_status,
            'seconds_remaining'=> $transaction->secondsRemaining(),
            'paid'             => $transaction->isSuccess(),
        ]);
    }

    /** Staff confirms customer has collected the order */
    public function confirmPickup(Request $request, Order $order): RedirectResponse    {
        if (! session('portal_station_id')) {
            return redirect()->route('pickup-portal.login');
        }

        // Security: ensure the order belongs to the logged-in station
        if ((int) $order->pickup_station_id !== (int) session('portal_station_id')) {
            abort(403, 'This order does not belong to your station.');
        }

        if ($order->status !== 'ready for pick up') {
            return back()->with('error', 'This order cannot be confirmed at this stage.');
        }

        $this->orders->markDelivered($order);

        return back()->with('success', "Order {$order->reference} confirmed as collected.");
    }
}
