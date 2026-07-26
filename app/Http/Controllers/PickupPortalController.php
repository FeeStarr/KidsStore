<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PickupStation;
use App\Models\RefundRequest;
use App\Notifications\PickupReminderNotification;
use App\Notifications\ReturnReminderNotification;
use App\Services\OPayService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\PickupStationService;
use App\Services\RefundService;
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
    public function __construct(
        private OrderService $orders,
        private OPayService $opay,
        private PaymentService $payments,
        private PickupStationService $pickupService,
        private RefundService $refunds
    ) {
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

    /** Dashboard — items for this station grouped by status */
    public function dashboard(Request $request): View|RedirectResponse
    {
        if (! session('portal_station_id')) {
            return redirect()->route('pickup-portal.login');
        }

        $stationId = (int) session('portal_station_id');
        $station = PickupStation::findOrFail($stationId);
        $filter = $request->input('filter', 'pending');

        $itemsByStatus = $this->pickupService->getItemsByStatus($stationId);
        $counts = [
            'pending' => $itemsByStatus['pending']->count(),
            'received' => $itemsByStatus['received']->count(),
            'ready' => $itemsByStatus['ready']->count(),
            'picked_up' => $itemsByStatus['picked_up']->count(),
        ];

        // Returns assigned to this station awaiting collection
        $pendingReturns = collect();
        try {
            $pendingReturns = RefundRequest::where('pickup_station_id', $stationId)
                ->where('status', RefundRequest::STATUS_APPROVED)
                ->with(['order', 'orderItem.product', 'orderItem.variant', 'order.customer'])
                ->latest()
                ->get();
        } catch (\Throwable $e) {
            // pickup_station_id column may not exist yet
        }

        $counts['returns'] = $pendingReturns->count();

        // Bank account for transfer payments
        $bankAccount = null;
        try {
            $bankAccount = BankAccount::where('is_active', true)
                ->where('is_default', true)
                ->first();
            if (! $bankAccount) {
                $bankAccount = BankAccount::where('is_active', true)->first();
            }
        } catch (\Throwable $e) {
            // bank_accounts table may not exist
        }

        $currentItems = $filter === 'returns'
            ? $pendingReturns
            : ($itemsByStatus[$filter] ?? collect());

        // Commission summary for picked-up tab
        $commissionSummary = null;
        if ($filter === 'picked_up') {
            $pickedUpItems = $itemsByStatus['picked_up'];
            $totalEarned = 0;
            $totalPaid = 0;
            $totalPending = 0;
            foreach ($pickedUpItems as $item) {
                $comm = $item->commission;
                $totalEarned += $comm;
                if ($item->pickup_station_fee_paid) {
                    $totalPaid += $comm;
                } else {
                    $totalPending += $comm;
                }
            }
            $commissionSummary = [
                'total_earned' => round($totalEarned, 2),
                'total_paid' => round($totalPaid, 2),
                'total_pending' => round($totalPending, 2),
                'paid_count' => $pickedUpItems->where('pickup_station_fee_paid', true)->count(),
                'pending_count' => $pickedUpItems->where('pickup_station_fee_paid', false)->count(),
            ];
        }

        return view('pickup-portal.dashboard', compact('station', 'filter', 'currentItems', 'counts', 'bankAccount', 'commissionSummary'));
    }

    /** AJAX data endpoint for dashboard items */
    public function dashboardData(Request $request)
    {
        if (! session('portal_station_id')) {
            return response()->json(['error' => 'Not authenticated.'], 401);
        }

        $stationId = (int) session('portal_station_id');
        $filter = $request->input('filter', 'pending');
        $itemsByStatus = $this->pickupService->getItemsByStatus($stationId);
        $items = $itemsByStatus[$filter] ?? collect();

        return response()->json([
            'data' => $items->map(fn ($item) => [
                'id' => $item->id,
                'order_ref' => $item->order?->reference,
                'customer' => $item->order?->customer?->name ?? '—',
                'product' => $item->product?->name,
                'variant' => $item->variant?->options_label,
                'quantity' => $item->quantity,
                'unit_price' => number_format($item->unit_price, 2),
                'commission' => number_format($item->commission, 2),
            ]),
        ]);
    }

    /** Data endpoint for DataTables — picked up items */
    public function pickedUpData(Request $request)
    {
        if (! session('portal_station_id')) return response()->json(['error' => 'Not authenticated.'], 401);
        $stationId = (int) session('portal_station_id');

        $q = OrderItem::whereHas('order', function ($q) use ($stationId) {
            $q->where('pickup_station_id', $stationId);
        })
        ->where('pickup_status', 'picked_up')
        ->with(['order', 'product', 'variant']);

        // Search
        $search = $request->input('search.value');
        if ($search) {
            $q->where(function ($sub) use ($search) {
                $sub->whereHas('product', fn($p) => $p->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('order', fn($o) => $o->where('reference', 'like', "%{$search}%"))
                    ->orWhereHas('variant', fn($v) => $v->where('options_label', 'like', "%{$search}%"));
            });
        }

        // Date filter
        $from = $request->input('from');
        $to = $request->input('to');
        if ($from) $q->whereDate('pickup_status_changed_at', '>=', $from);
        if ($to) $q->whereDate('pickup_status_changed_at', '<=', $to);

        $recordsTotal = $q->count();
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 25);

        $items = $q->orderByDesc('pickup_status_changed_at')->offset($start)->limit($length)->get();

        $data = $items->map(function($item) {
            return [
                'id' => $item->id,
                'order_reference' => $item->order?->reference,
                'customer' => $item->order?->customer?->name ?? '—',
                'product' => $item->product?->name,
                'variant' => $item->variant?->options_label ?? '—',
                'quantity' => $item->quantity,
                'line_total' => number_format($item->line_total, 2),
                'commission' => number_format($item->commission, 2),
                'is_paid' => $item->pickup_station_fee_paid,
                'status' => $item->pickup_station_fee_paid ? 'Paid' : 'Pending',
                'status_class' => $item->pickup_station_fee_paid ? 'bg-success' : 'bg-warning text-dark',
                'picked_up_at' => $item->pickup_status_changed_at?->format('M d, Y H:i'),
            ];
        })->values();

        return response()->json([
            'draw' => (int) $request->input('draw', 0),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsTotal,
            'data' => $data,
        ]);
    }

    /** Export picked up items as CSV */
    public function pickedUpExport(Request $request)
    {
        if (! session('portal_station_id')) return redirect()->route('pickup-portal.login');
        $stationId = (int) session('portal_station_id');

        $items = OrderItem::whereHas('order', function ($q) use ($stationId) {
            $q->where('pickup_station_id', $stationId);
        })
        ->where('pickup_status', 'picked_up')
        ->with(['order', 'product', 'variant'])
        ->orderByDesc('pickup_status_changed_at')
        ->get();

        $filename = 'picked_up_items_' . now()->format('Ymd_His') . '.csv';

        $callback = function () use ($items) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Order', 'Customer', 'Product', 'Variant', 'Qty', 'Line Total', 'Commission (10%)', 'Status', 'Picked Up At']);

            foreach ($items as $item) {
                fputcsv($handle, [
                    $item->order?->reference,
                    $item->order?->customer?->name ?? '—',
                    $item->product?->name,
                    $item->variant?->options_label ?? '—',
                    $item->quantity,
                    number_format($item->line_total, 2),
                    number_format($item->commission, 2),
                    $item->pickup_station_fee_paid ? 'Paid' : 'Pending',
                    $item->pickup_status_changed_at?->format('Y-m-d H:i'),
                ]);
            }
            fclose($handle);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /** Mark an item as received */
    public function markReceived(Request $request, OrderItem $item): RedirectResponse
    {
        if (! session('portal_station_id')) {
            return redirect()->route('pickup-portal.login');
        }

        try {
            $this->pickupService->markReceived($item, (int) session('portal_station_id'));
            return back()->with('success', 'Item marked as received.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /** Mark an item as ready for pickup */
    public function markReady(Request $request, OrderItem $item): RedirectResponse
    {
        if (! session('portal_station_id')) {
            return redirect()->route('pickup-portal.login');
        }

        try {
            $this->pickupService->markReady($item, (int) session('portal_station_id'));
            return back()->with('success', 'Item marked as ready for pickup.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /** Mark an item as picked up by customer */
    public function markPickedUp(Request $request, OrderItem $item): RedirectResponse
    {
        if (! session('portal_station_id')) {
            return redirect()->route('pickup-portal.login');
        }

        try {
            $this->pickupService->markPickedUp($item, (int) session('portal_station_id'));
            return back()->with('success', 'Item marked as picked up. Commission recorded.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /** Mark multiple items as received (bulk action) */
    public function bulkMarkReceived(Request $request): RedirectResponse
    {
        if (! session('portal_station_id')) {
            return redirect()->route('pickup-portal.login');
        }

        $data = $request->validate([
            'item_ids' => ['required', 'array'],
            'item_ids.*' => ['integer', 'exists:order_items,id'],
        ]);

        $stationId = (int) session('portal_station_id');
        $success = 0;
        $errors = [];

        foreach ($data['item_ids'] as $itemId) {
            try {
                $item = OrderItem::findOrFail($itemId);
                $this->pickupService->markReceived($item, $stationId);
                $success++;
            } catch (\RuntimeException $e) {
                $errors[] = "Item #{$itemId}: {$e->getMessage()}";
            }
        }

        $message = "{$success} item(s) marked as received.";
        if ($errors) {
            $message .= ' Errors: ' . implode(' ', $errors);
        }

        return back()->with($success > 0 ? 'success' : 'error', $message);
    }

    /** Mark multiple items as ready (bulk action) */
    public function bulkMarkReady(Request $request): RedirectResponse
    {
        if (! session('portal_station_id')) {
            return redirect()->route('pickup-portal.login');
        }

        $data = $request->validate([
            'item_ids' => ['required', 'array'],
            'item_ids.*' => ['integer', 'exists:order_items,id'],
        ]);

        $stationId = (int) session('portal_station_id');
        $success = 0;
        $errors = [];

        foreach ($data['item_ids'] as $itemId) {
            try {
                $item = OrderItem::findOrFail($itemId);
                $this->pickupService->markReady($item, $stationId);
                $success++;
            } catch (\RuntimeException $e) {
                $errors[] = "Item #{$itemId}: {$e->getMessage()}";
            }
        }

        $message = "{$success} item(s) marked as ready.";
        if ($errors) {
            $message .= ' Errors: ' . implode(' ', $errors);
        }

        return back()->with($success > 0 ? 'success' : 'error', $message);
    }

    /** Payouts page for station */
    public function payouts(Request $request): View|RedirectResponse
    {
        if (! session('portal_station_id')) return redirect()->route('pickup-portal.login');
        $stationId = (int) session('portal_station_id');

        $payoutSummary = $this->pickupService->getPayoutSummary($stationId);

        return view('pickup-portal.payouts', compact('payoutSummary'));
    }

    /** Server-side data for payouts DataTable */
    public function payoutsData(Request $request)
    {
        if (! session('portal_station_id')) return response()->json(['error' => 'Not authenticated.'], 401);
        $stationId = (int) session('portal_station_id');

        $q = PickupPayout::select('id', 'reference', 'amount', 'note', 'created_at', 'is_reversed')
            ->where('pickup_station_id', $stationId)
            ->with(['items.orderItem.product', 'items.orderItem.variant', 'items.order']);

        // Status filter
        $status = $request->input('status');
        if ($status === 'reversed') $q->where('is_reversed', true);
        if ($status === 'paid') $q->where('is_reversed', false);

        // Date filters
        $from = $request->input('from');
        $to = $request->input('to');
        if ($from) $q->whereDate('created_at', '>=', $from);
        if ($to) $q->whereDate('created_at', '<=', $to);

        // Search
        $search = $request->input('search.value');
        if ($search) {
            $q->where(function ($sub) use ($search) {
                $sub->where('reference', 'like', "%{$search}%")
                    ->orWhere('note', 'like', "%{$search}%")
                    ->orWhereHas('items.orderItem.product', fn($p) => $p->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('items.order', fn($o) => $o->where('reference', 'like', "%{$search}%"));
            });
        }

        $recordsTotal = PickupPayout::where('pickup_station_id', $stationId)->count();
        $recordsFiltered = (clone $q)->count();
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 25);

        $rows = $q->orderByDesc('created_at')->offset($start)->limit($length)->get();

        $data = $rows->map(function($p) {
            $items = $p->items;
            $itemCount = $items->count();
            $orderRefs = $items->pluck('order.reference')->filter()->unique()->values();
            $productNames = $items->pluck('orderItem.product.name')->filter()->unique()->take(3)->implode(', ');

            return [
                'id' => $p->id,
                'reference' => $p->reference,
                'date' => $p->created_at?->format('M d, Y'),
                'amount' => '₦' . number_format($p->amount, 2),
                'status' => $p->is_reversed ? 'Reversed' : 'Paid',
                'status_class' => $p->is_reversed ? 'bg-danger' : 'bg-success',
                'note' => $p->note ?? '—',
                'orders' => $orderRefs->implode(', ') ?: '—',
                'item_count' => $itemCount,
                'products' => $productNames . ($itemCount > 3 ? ' +' . ($itemCount - 3) . ' more' : ''),
                'items_detail' => $items->map(fn($it) => [
                    'product' => $it->orderItem?->product?->name ?? '—',
                    'variant' => $it->orderItem?->variant?->options_label ?? '—',
                    'fee' => '₦' . number_format($it->fee_amount, 2),
                    'order' => $it->order?->reference ?? '—',
                ])->values(),
            ];
        })->values();

        return response()->json([
            'draw' => (int) $request->input('draw', 0),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    /** Mark selected orders as paid and create a pickup payout (portal) */
    public function markPaid(Request $request): RedirectResponse
    {
        if (! session('portal_station_id')) return redirect()->route('pickup-portal.login');
        $stationId = (int) session('portal_station_id');

        $data = $request->validate([
            'order_ids' => ['required','array'],
            'order_ids.*' => ['integer','exists:orders,id'],
            'note' => ['nullable','string'],
        ]);

        $orderIds = $data['order_ids'];

        $orders = Order::with('items.variant.product')->whereIn('id', $orderIds)->where('pickup_station_id', $stationId)->get();
        if ($orders->isEmpty()) return back()->with('error','No matching orders found.');

        $total = 0;
        $itemsToMarkPaid = collect();

        foreach ($orders as $o) {
            $pickedUpItems = $o->items->where('pickup_status', 'picked_up')->where('pickup_station_fee_paid', false);
            foreach ($pickedUpItems as $item) {
                $commission = $item->commission;
                $total += $commission;
                $itemsToMarkPaid->push($item);
            }
        }

        if ($total <= 0) {
            return back()->with('error', 'No picked-up items found for the selected orders.');
        }

        $payout = PickupPayout::create([
            'pickup_station_id' => $stationId,
            'amount' => round($total, 2),
            'created_by' => null,
            'reference' => 'PP-'.Str::upper(substr((string) Str::uuid(), 0, 8)),
            'note' => $data['note'] ?? null,
        ]);

        foreach ($itemsToMarkPaid as $item) {
            PickupPayoutItem::create([
                'pickup_payout_id' => $payout->id,
                'order_id' => $item->order_id,
                'order_item_id' => $item->id,
                'fee_amount' => $item->commission,
            ]);

            $item->update([
                'pickup_station_fee_paid' => true,
                'pickup_station_fee_paid_at' => now(),
            ]);
        }

        // Refresh order-level fee total once per affected order
        $affectedOrderIds = $itemsToMarkPaid->pluck('order_id')->unique();
        foreach ($affectedOrderIds as $oid) {
            $order = Order::find($oid);
            if ($order) $this->pickupService->refreshOrderFeeTotal($order);
        }

        return back()->with('success', "Payout record created for ₦" . number_format($total, 2) . " ({$itemsToMarkPaid->count()} items).");
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

    /** Record a payment (cash/transfer) at the station */
    public function recordPayment(Request $request, Order $order): RedirectResponse
    {
        if (! session('portal_station_id')) return redirect()->route('pickup-portal.login');
        if ((int)$order->pickup_station_id !== (int) session('portal_station_id')) abort(403);

        $data = $request->validate([
            'amount' => ['required','numeric','min:0.01'],
            'method' => ['required','string'],
            'note' => ['nullable','string'],
        ]);

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
    public function confirmPickup(Request $request, Order $order): RedirectResponse
    {
        if (! session('portal_station_id')) {
            return redirect()->route('pickup-portal.login');
        }

        if ((int) $order->pickup_station_id !== (int) session('portal_station_id')) {
            abort(403, 'This order does not belong to your station.');
        }

        if ($order->status !== 'ready for pick up') {
            return back()->with('error', 'This order cannot be confirmed at this stage.');
        }

        $this->orders->markDelivered($order);

        return back()->with('success', "Order {$order->reference} confirmed as collected.");
    }

    /** Show return details for the station */
    public function returnDetails(RefundRequest $refundRequest): View|RedirectResponse
    {
        if (! session('portal_station_id')) {
            return redirect()->route('pickup-portal.login');
        }

        $stationId = (int) session('portal_station_id');

        if ($refundRequest->pickup_station_id !== $stationId) {
            abort(403, 'This return is not assigned to your station.');
        }

        $refundRequest->load([
            'order', 'orderItem.product', 'orderItem.variant', 'order.customer',
        ]);

        return view('pickup-portal.return-details', ['refundRequest' => $refundRequest]);
    }

    /** Station marks a return as collected from customer */
    public function collectReturn(Request $request, RefundRequest $refundRequest): RedirectResponse
    {
        if (! session('portal_station_id')) {
            return redirect()->route('pickup-portal.login');
        }

        $stationId = (int) session('portal_station_id');

        try {
            $this->refunds->collectReturn($refundRequest, $stationId);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Return item collected. Admin has been notified.');
    }

    /** Send a pickup reminder email to the customer */
    public function sendReminder(Order $order): RedirectResponse
    {
        if (! session('portal_station_id')) {
            return redirect()->route('pickup-portal.login');
        }

        $stationId = (int) session('portal_station_id');

        if ((int) $order->pickup_station_id !== $stationId) {
            abort(403, 'This order does not belong to your station.');
        }

        if (! $order->customer) {
            return back()->with('error', 'No customer associated with this order.');
        }

        try {
            $order->customer->notify(new PickupReminderNotification($order));
            return back()->with('success', 'Pickup reminder sent to ' . $order->customer->name . '.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to send reminder: ' . $e->getMessage());
        }
    }

    /** Send a return reminder email to the customer */
    public function sendReturnReminder(RefundRequest $return): RedirectResponse
    {
        if (! session('portal_station_id')) {
            return redirect()->route('pickup-portal.login');
        }

        $stationId = (int) session('portal_station_id');

        if ((int) $return->pickup_station_id !== $stationId) {
            abort(403, 'This return does not belong to your station.');
        }

        $customer = $return->order?->customer;

        if (! $customer) {
            return back()->with('error', 'No customer associated with this return.');
        }

        try {
            $customer->notify(new ReturnReminderNotification($return));
            return back()->with('success', 'Return reminder sent to ' . $customer->name . '.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to send reminder: ' . $e->getMessage());
        }
    }
}
