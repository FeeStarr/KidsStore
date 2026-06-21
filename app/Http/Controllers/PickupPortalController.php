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

        $bankAccounts = \App\Models\BankAccount::active()->orderByDesc('is_default')->get();
        $payoutCount = \App\Models\PickupPayout::where('pickup_station_id', $stationId)->count();
        $highlightOrderId = $request->input('order_id');
        return view('pickup-portal.dashboard', compact('orders', 'filter', 'readyCount', 'bankAccounts', 'payoutCount', 'highlightOrderId'));
    }

    /** Server-side DataTables source for orders (pickup portal) */
    public function dashboardData(Request $request)
    {
        if (! session('portal_station_id')) return response()->json(['error' => 'Unauthenticated'], 401);
        $stationId = (int) session('portal_station_id');

        $columns = ['id','reference','order_date','customer_name','grand_total','status','payment_status'];

        $query = \App\Models\Order::with(['items.product','items.variant','customer'])
            ->where('pickup_station_id', $stationId)
            ->where('delivery_method','pickup');

        // filters
        $status = $request->input('status');
        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }
        $paymentStatus = $request->input('payment_status');
        if ($paymentStatus && $paymentStatus !== 'all') {
            $query->where('payment_status', $paymentStatus);
        }
        $from = $request->input('from');
        $to = $request->input('to');
        if ($from) $query->whereDate('order_date', '>=', $from);
        if ($to) $query->whereDate('order_date', '<=', $to);

        $search = $request->input('search.value');
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('reference','like', "%{$search}%")
                  ->orWhere('customer_name','like', "%{$search}%");
            });
        }

        // records count
        $totalQuery = \App\Models\Order::where('pickup_station_id', $stationId)->where('delivery_method','pickup');
        $recordsTotal = $totalQuery->count();
        $recordsFiltered = $query->count();

        $orderCol = (int)($request->input('order.0.column') ?? 2);
        $orderDir = $request->input('order.0.dir','desc');
        $orderBy = $columns[$orderCol] ?? 'order_date';

        $start = (int)$request->input('start',0);
        $length = (int)$request->input('length',10);

        $rows = $query->orderBy($orderBy, $orderDir)->skip($start)->take($length)->get();

        $data = $rows->map(function($order){
            return [
                'id' => $order->id,
                'reference' => $order->reference,
                'order_date' => $order->order_date->toDateString(),
                'customer' => $order->customer?->name ?? null,
                'grand_total' => number_format($order->grand_total,2),
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'balance' => $order->balance,
                'is_ready' => $order->status === 'ready for pick up',
                'items' => $order->items->map(function($it){
                    return [
                        'product' => $it->product?->name ?? null,
                        'variant' => $it->variant?->options_label ?? null,
                        'quantity' => $it->quantity,
                        'line_total' => number_format($it->line_total,2),
                    ];
                })->toArray(),
                'actions' => [
                    'confirm_url' => route('pickup-portal.confirm', $order),
                    'initiate_url' => route('pickup-portal.initiate-payment', $order),
                    'query_url' => route('pickup-portal.query-payment', $order),
                    'record_payment_url' => route('pickup-portal.record-payment', $order),
                ],
            ];
        })->toArray();

        return response()->json([
            'draw' => (int)$request->input('draw',0),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    /** Show payment history for the logged-in station */
    public function payments(Request $request): View|RedirectResponse
    {
        if (! session('portal_station_id')) return redirect()->route('pickup-portal.login');

        $stationId = (int) session('portal_station_id');

        $payments = \App\Models\Payment::query()
            ->join('orders', 'payments.order_id', '=', 'orders.id')
            ->where('orders.pickup_station_id', $stationId)
            ->where('orders.delivery_method', 'pickup')
            ->select('payments.*')
            ->orderByDesc('payments.payment_date')
            ->get();

        return view('pickup-portal.payments', compact('payments'));
    }

    /** Show delivery history for logged-in station */
    public function deliveries(Request $request): View|RedirectResponse
    {
        if (! session('portal_station_id')) return redirect()->route('pickup-portal.login');

        $stationId = (int) session('portal_station_id');

        $orders = \App\Models\Order::with('items')
            ->where('pickup_station_id', $stationId)
            ->where('delivery_method', 'pickup')
            ->whereIn('status', ['delivered','ready for pick up','processing','confirmed'])
            ->orderByDesc('order_date')
            ->get();

        return view('pickup-portal.deliveries', compact('orders'));
    }

    /** Staff records a manual payment for an order (cash/transfer) */
    public function recordPayment(Request $request, \App\Models\Order $order)
    {
        if (! session('portal_station_id')) return response()->redirectToRoute('pickup-portal.login');
        if ((int) $order->pickup_station_id !== (int) session('portal_station_id')) abort(403);

        $data = $request->validate([
            'amount' => ['required','numeric','min:0.01'],
            'method' => ['nullable','string'],
            'transaction_id' => ['nullable','string'],
        ]);

        app(\App\Services\PaymentService::class)->record($order, [
            'payment_date' => now()->toDateString(),
            'amount' => (float) $data['amount'],
            'method' => $data['method'] ?? 'cash',
            'transaction_id' => $data['transaction_id'] ?? null,
            'note' => 'Recorded by pickup staff',
        ]);

        return back()->with('success', 'Payment recorded.');
    }

    /** Show payouts (fees paid to the station) */
    public function payouts(Request $request)
    {
        if (! session('portal_station_id')) return redirect()->route('pickup-portal.login');

        $stationId = (int) session('portal_station_id');

        $status = $request->input('status', 'paid'); // paid|pending|all
        $from = $request->input('from');
        $to = $request->input('to');

        $payouts = collect();
        $pendingOrders = collect();

        if ($status === 'paid' || $status === 'all') {
            $q = \App\Models\PickupPayout::with(['items.order','items.orderItem.product'])
                ->where('pickup_station_id', $stationId);
            if ($from) $q->whereDate('created_at', '>=', $from);
            if ($to) $q->whereDate('created_at', '<=', $to);
            $payouts = $q->orderByDesc('created_at')->paginate(15);
        }

        if ($status === 'pending' || $status === 'all') {
            $station = PickupStation::find($stationId);
            $feePct = $station?->fee_pct ?? 0;

            $oq = \App\Models\Order::with('items')
                ->where('pickup_station_id', $stationId)
                ->where('delivery_method', 'pickup')
                ->whereHas('items', function ($q) {
                    $q->where('pickup_station_fee_paid', false);
                });

            if ($from) $oq->whereDate('order_date', '>=', $from);
            if ($to) $oq->whereDate('order_date', '<=', $to);

            $pendingOrders = $oq->orderByDesc('order_date')->paginate(15);
            // Transform the paginator's collection to include fee_amount
            $pendingOrders->setCollection($pendingOrders->getCollection()->map(function ($o) use ($feePct) {
                $fee = round($o->grand_total * ((float)$feePct) / 100, 2);
                return (object) [
                    'order' => $o,
                    'fee_amount' => $fee,
                ];
            }));
        }

        return view('pickup-portal.payouts', compact('payouts', 'pendingOrders', 'status', 'from', 'to'));
    }

    /** Server-side DataTables source for payouts */
    public function payoutsData(Request $request)
    {
        if (! session('portal_station_id')) return response()->json(['error' => 'Unauthenticated'], 401);

        $stationId = (int) session('portal_station_id');
        $status = $request->input('status', 'paid');
        $from = $request->input('from');
        $to = $request->input('to');

        $columns = [
            'id','reference','created_at','amount','is_reversed','note'
        ];

        $query = \App\Models\PickupPayout::where('pickup_station_id', $stationId);
        if ($from) $query->whereDate('created_at', '>=', $from);
        if ($to) $query->whereDate('created_at', '<=', $to);
        if ($status === 'reversed') {
            $query->where('is_reversed', true);
        } elseif ($status === 'paid') {
            $query->where('is_reversed', false);
        } elseif ($status === 'pending') {
            // pending means no payouts — return empty set
            $query->whereNull('id');
        }

        $recordsTotal = $query->count();

        // filtering (search)
        $search = $request->input('search.value');
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                  ->orWhere('note', 'like', "%{$search}%");
            });
        }

        $recordsFiltered = $query->count();

        // ordering
        $orderCol = (int)($request->input('order.0.column') ?? 2);
        $orderDir = $request->input('order.0.dir', 'desc');
        $orderBy = $columns[$orderCol] ?? 'created_at';

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);

        $rows = $query->orderBy($orderBy, $orderDir)
            ->skip($start)->take($length)
            ->with('items.order')
            ->get();

        $data = $rows->map(function($payout) {
            $items = $payout->items->map(function($it){
                $order = $it->order; return [
                    'order_id' => $it->order_id,
                    'order_reference' => $order->reference ?? 'Order #'.$it->order_id,
                    'order_amount' => $order->grand_total ?? null,
                    'fee_amount' => $it->fee_amount,
                ];
            })->toArray();

            $ordersArr = collect($items)->map(function($i){ return ['id' => $i['order_id'], 'reference' => $i['order_reference']]; })
                ->unique('reference')->values()->slice(0,5)->all();

            return [
                'id' => $payout->id,
                'reference' => $payout->reference,
                'orders' => $ordersArr,
                'date' => $payout->created_at->toDateTimeString(),
                'amount' => number_format($payout->amount,2),
                'is_reversed' => (bool)$payout->is_reversed,
                'note' => $payout->note,
                'items' => $items,
            ];
        })->toArray();

        return response()->json([
            'draw' => (int) $request->input('draw', 0),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    /** Staff marks selected pending orders as paid and creates a payout record */
    public function markPaid(Request $request)
    {
        if (! session('portal_station_id')) return redirect()->route('pickup-portal.login');

        $stationId = (int) session('portal_station_id');

        $data = $request->validate([
            'order_ids' => ['required','array'],
            'order_ids.*' => ['required','integer','exists:orders,id'],
            'note' => ['nullable','string'],
        ]);

        $orders = \App\Models\Order::with('items')->whereIn('id', $data['order_ids'])->get();

        // Ensure all orders belong to station
        foreach ($orders as $o) {
            if ((int) $o->pickup_station_id !== $stationId) abort(403);
        }

        $station = PickupStation::find($stationId);
        $feePct = $station?->fee_pct ?? 0;

        $total = 0;
        foreach ($orders as $o) {
            $fee = round($o->grand_total * ((float)$feePct) / 100, 2);
            $total += $fee;
        }

        $payout = \App\Models\PickupPayout::create([
            'pickup_station_id' => $stationId,
            'amount' => $total,
            'created_by' => auth()->id(),
            'reference' => 'PP-'.str_pad((string)((int) (\App\Models\PickupPayout::max('id') + 1)), 6, '0', STR_PAD_LEFT),
            'note' => $data['note'] ?? null,
        ]);

        foreach ($orders as $o) {
            $fee = round($o->grand_total * ((float)$feePct) / 100, 2);
            \App\Models\PickupPayoutItem::create([
                'pickup_payout_id' => $payout->id,
                'order_id' => $o->id,
                'fee_amount' => $fee,
            ]);

            \App\Models\OrderItem::where('order_id', $o->id)->update([
                'pickup_station_fee_paid' => true,
                'pickup_station_fee_paid_at' => now(),
            ]);
        }

        return back()->with('success', 'Recorded payout '.$payout->reference);
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
