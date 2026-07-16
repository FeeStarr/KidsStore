<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PickupStation;
use App\Services\OPayService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Models\Payment;
use App\Models\PickupPayout;
use App\Models\PickupPayoutItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class PickupPortalController extends Controller
{
    public function __construct(private OrderService $orders, private OPayService $opay, private PaymentService $payments)
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

    /** Data endpoint for DataTables on dashboard */
    public function dashboardData(Request $request)
    {
        if (! session('portal_station_id')) return response()->json(['error' => 'Not authenticated.'], 401);
        $stationId = (int) session('portal_station_id');

        $q = Order::with('items')->where('pickup_station_id', $stationId)->where('delivery_method', 'pickup');

        // filters
        $status = $request->input('status');
        if ($status && $status !== 'all') $q->where('status', $status);
        $payment = $request->input('payment_status');
        if ($payment && $payment !== 'all') $q->where('payment_status', $payment);
        $from = $request->input('from');
        $to = $request->input('to');
        if ($from) $q->whereDate('order_date', '>=', $from);
        if ($to) $q->whereDate('order_date', '<=', $to);

        $recordsTotal = $q->count();

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 25);

        $orders = $q->orderByDesc('order_date')->offset($start)->limit($length)->get();

        $data = $orders->map(function($o){
            return [
                'id' => $o->id,
                'reference' => $o->reference,
                'order_date' => $o->order_date?->toDateString(),
                'customer' => $o->customer?->name,
                'grand_total' => (float) $o->grand_total,
                'status' => $o->status,
                'payment_status' => $o->payment_status,
                'items' => $o->items->map(fn($it)=> [
                    'product' => $it->product?->name,
                    'variant' => $it->variant?->options_label,
                    'quantity' => $it->quantity,
                    'line_total' => (float) $it->line_total,
                ])->values()->all(),
            ];
        })->values();

        return response()->json([
            'draw' => (int) $request->input('draw', 0),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsTotal,
            'data' => $data,
        ]);
    }

    /** Payments list for this station */
    public function payments(Request $request): View|RedirectResponse
    {
        if (! session('portal_station_id')) return redirect()->route('pickup-portal.login');
        $stationId = (int) session('portal_station_id');

        $payments = Payment::with('order')->whereHas('order', fn($q)=> $q->where('pickup_station_id', $stationId))->orderByDesc('payment_date')->get();

        return view('pickup-portal.payments', compact('payments'));
    }

    /** Deliveries list (orders for station) */
    public function deliveries(Request $request): View|RedirectResponse
    {
        if (! session('portal_station_id')) return redirect()->route('pickup-portal.login');
        $stationId = (int) session('portal_station_id');

        $orders = Order::with('customer','items')->where('pickup_station_id', $stationId)
            ->where('delivery_method','pickup')
            ->whereIn('status', ['ready for pick up','processing','confirmed'])
            ->orderByDesc('order_date')->get();

        return view('pickup-portal.deliveries', compact('orders'));
    }

    /** Payouts page for station */
    public function payouts(Request $request)
    {
        if (! session('portal_station_id')) return redirect()->route('pickup-portal.login');
        $stationId = (int) session('portal_station_id');

        $status = $request->input('status', 'paid');
        $from = $request->input('from');
        $to = $request->input('to');

        if ($status === 'pending') {
            // pending orders with unpaid items
            $pending = Order::with('items')->where('pickup_station_id', $stationId)
                ->where('delivery_method','pickup')
                ->whereHas('items', fn($q)=> $q->where('pickup_station_fee_paid', false));

            if ($from) $pending->whereDate('order_date','>=',$from);
            if ($to) $pending->whereDate('order_date','<=',$to);

            $pendingOrders = $pending->orderByDesc('order_date')->get()->map(fn($o)=> [
                'order' => $o,
                'fee_amount' => round($o->pickup_station_fee_total ?: 0, 2),
            ]);

            return view('pickup-portal.payouts', ['pendingOrders' => $pendingOrders, 'status' => $status, 'from' => $from, 'to' => $to]);
        }

        // for paid/reversed/all, pass an empty pendingOrders collection and let DataTable fetch via payoutsData
        $pendingOrders = collect();
        return view('pickup-portal.payouts', compact('pendingOrders','status','from','to'));
    }

    /** Server-side data for payouts DataTable */
    public function payoutsData(Request $request)
    {
        if (! session('portal_station_id')) return response()->json(['error' => 'Not authenticated.'], 401);
        $stationId = (int) session('portal_station_id');

        $q = PickupPayout::with(['items','station'])->where('pickup_station_id', $stationId);
        $status = $request->input('status');
        if ($status === 'reversed') $q->where('is_reversed', true);
        if ($status === 'paid') $q->where('is_reversed', false);

        $recordsTotal = $q->count();
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 25);

        $rows = $q->orderByDesc('created_at')->offset($start)->limit($length)->get();

        $data = $rows->map(function($p){
            return [
                'id' => $p->id,
                'reference' => $p->reference,
                'date' => $p->created_at?->toDateString(),
                'amount' => (float) $p->amount,
                'is_reversed' => (bool) $p->is_reversed,
                'note' => $p->note,
                'items' => $p->items->map(fn($it)=> [
                    'order_id' => $it->order_id,
                    'order_reference' => $it->order?->reference,
                    'order_amount' => $it->order?->grand_total,
                    'fee_amount' => $it->fee_amount,
                ])->values()->all(),
            ];
        })->values();

        return response()->json([
            'draw' => (int) $request->input('draw', 0),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsTotal,
            'data' => $data,
        ]);
    }

    /** Mark selected orders as paid and create a pickup payout (portal) */
    public function markPaid(Request $request)
    {
        if (! session('portal_station_id')) return redirect()->route('pickup-portal.login');
        $stationId = (int) session('portal_station_id');

        $data = $request->validate([
            'order_ids' => ['required','array'],
            'order_ids.*' => ['integer','exists:orders,id'],
            'note' => ['nullable','string'],
        ]);

        $orderIds = $data['order_ids'];

        $orders = Order::with('items')->whereIn('id', $orderIds)->where('pickup_station_id', $stationId)->get();
        if ($orders->isEmpty()) return back()->with('error','No matching orders found.');

        $total = 0;
        foreach ($orders as $o) {
            $total += round($o->pickup_station_fee_total ?: 0, 2);
        }

        $payout = PickupPayout::create([
            'pickup_station_id' => $stationId,
            'amount' => $total,
            'created_by' => null,
            'reference' => 'PP-'.Str::upper(substr((string) Str::uuid(), 0, 8)),
            'note' => $data['note'] ?? null,
        ]);

        foreach ($orders as $o) {
            PickupPayoutItem::create([
                'pickup_payout_id' => $payout->id,
                'order_id' => $o->id,
                'fee_amount' => round($o->pickup_station_fee_total ?: 0, 2),
            ]);

            // mark all items as paid
            DB::table('order_items')->where('order_id', $o->id)->update([
                'pickup_station_fee_paid' => true,
                'pickup_station_fee_paid_at' => now(),
            ]);
        }

        return back()->with('success','Marked selected orders as paid and created payout record.');
    }

    /** Record a payment (cash/transfer) at the station */
    public function recordPayment(Request $request, Order $order)
    {
        if (! session('portal_station_id')) return redirect()->route('pickup-portal.login');
        if ((int)$order->pickup_station_id !== (int) session('portal_station_id')) abort(403);

        $data = $request->validate([
            'amount' => ['required','numeric','min:0.01'],
            'method' => ['required','string'],
            'note' => ['nullable','string'],
        ]);

        // create Payment via PaymentService
        $payment = $this->payments->record($order, [
            'payment_date' => now()->toDateString(),
            'amount' => (float) $data['amount'],
            'method' => $data['method'],
            'note' => $data['note'] ?? null,
        ]);

        return back()->with('success', 'Payment recorded.');
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
