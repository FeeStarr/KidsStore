<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PickupStation;
use App\Models\OrderItem;
use App\Services\PickupStationService;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PickupPayoutController extends Controller
{
    public function __construct(private PickupStationService $pickupService)
    {
    }

    /** Show aggregated amounts per station based on picked_up items */
    public function index(Request $request): View
    {
        $stations = PickupStation::orderBy('name')->get();

        $summary = $stations->map(function ($s) {
            $payoutSummary = $this->pickupService->getPayoutSummary($s->id);

            return [
                'station' => $s,
                'total_earned' => $payoutSummary['total_earned'],
                'total_paid_out' => $payoutSummary['total_paid_out'],
                'balance_due' => $payoutSummary['balance_due'],
                'item_count' => $payoutSummary['item_count'],
            ];
        });

        return view('admin.payouts.index', compact('summary'));
    }

    /** Show details for a station — item-level commission breakdown */
    public function show(Request $request, PickupStation $pickupStation): View
    {
        $payoutSummary = $this->pickupService->getPayoutSummary($pickupStation->id);

        return view('admin.payouts.show', compact('pickupStation', 'payoutSummary'));
    }

    /** DataTable endpoint for station commission breakdown */
    public function showData(Request $request, PickupStation $pickupStation)
    {
        $q = OrderItem::whereHas('order', function ($q) use ($pickupStation) {
            $q->where('pickup_station_id', $pickupStation->id);
        })
        ->where('pickup_status', 'picked_up')
        ->with(['order', 'product', 'variant']);

        // Filter by paid status
        $paidStatus = $request->input('paid_status');
        if ($paidStatus === 'paid') $q->where('pickup_station_fee_paid', true);
        if ($paidStatus === 'unpaid') $q->where('pickup_station_fee_paid', false);

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

        $recordsTotal = OrderItem::whereHas('order', function ($q) use ($pickupStation) {
            $q->where('pickup_station_id', $pickupStation->id);
        })->where('pickup_status', 'picked_up')->count();

        $recordsFiltered = (clone $q)->count();
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 25);

        $items = $q->orderByDesc('pickup_status_changed_at')->offset($start)->limit($length)->get();

        $data = $items->map(function ($item) {
            $isPaid = $item->pickup_station_fee_paid;
            return [
                'order_reference' => $item->order?->reference ? '<a href="' . route('admin.orders.show', $item->order_id) . '">' . e($item->order->reference) . '</a>' : '—',
                'order_date' => $item->order?->order_date?->format('M d, Y'),
                'product' => e($item->product?->name ?? '—'),
                'variant' => e($item->variant?->options_label ?? '—'),
                'quantity' => $item->quantity,
                'unit_price' => '₦' . number_format($item->unit_price, 2),
                'line_total' => '₦' . number_format($item->line_total, 2),
                'commission' => '₦' . number_format($item->commission, 2),
                'is_paid' => $isPaid,
                'status' => $isPaid ? 'Paid' : 'Unpaid',
                'status_class' => $isPaid ? 'bg-success' : 'bg-warning text-dark',
                'checkbox' => $isPaid ? '' : '<input type="checkbox" name="order_item_ids[]" value="' . $item->id . '" class="form-check-input item-paid-check">',
                'picked_up_at' => $item->pickup_status_changed_at?->format('M d, Y H:i'),
            ];
        })->values();

        return response()->json([
            'draw' => (int) $request->input('draw', 0),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    /** Mark selected items as paid (bulk) — pays commission on picked_up items */
    public function markPaid(Request $request, PickupStation $pickupStation)
    {
        $data = $request->validate([
            'order_item_ids' => ['required', 'array'],
            'order_item_ids.*' => ['integer', 'exists:order_items,id'],
            'note' => ['nullable', 'string'],
        ]);

        $orderItemIds = $data['order_item_ids'];

        $itemsToMarkPaid = \App\Models\OrderItem::whereIn('id', $orderItemIds)
            ->where('pickup_status', 'picked_up')
            ->where('pickup_station_fee_paid', false)
            ->whereHas('order', fn($q) => $q->where('pickup_station_id', $pickupStation->id))
            ->get();

        if ($itemsToMarkPaid->isEmpty()) {
            return back()->with('error', 'No unpaid picked-up items found for the selected items.');
        }

        // Apply per-order commission cap from settings
        $commMin = (float) \App\Models\Setting::get('commission_min', 500);
        $commMax = (float) \App\Models\Setting::get('commission_max', 2000);
        $byOrder = [];
        foreach ($itemsToMarkPaid as $item) {
            $byOrder[$item->order_id] = ($byOrder[$item->order_id] ?? 0) + $item->commission;
        }
        $total = 0;
        foreach ($byOrder as $orderComm) {
            $total += max($commMin, min($commMax, $orderComm));
        }

        $payout = \App\Models\PickupPayout::create([
            'pickup_station_id' => $pickupStation->id,
            'amount' => round($total, 2),
            'created_by' => auth()->id(),
            'reference' => 'PP-' . Str::upper(substr((string) Str::uuid(), 0, 8)),
            'note' => $data['note'] ?? null,
        ]);

        // Create payout items for each picked_up item and mark as paid
        foreach ($itemsToMarkPaid as $item) {
            \App\Models\PickupPayoutItem::create([
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
            $order = \App\Models\Order::find($oid);
            if ($order) $this->pickupService->refreshOrderFeeTotal($order);
        }

        return back()->with('success', "Payout record created for ₦" . number_format($total, 2) . " ({$itemsToMarkPaid->count()} items).");
    }

    /** List recorded payouts (ledger) for admin with filters */
    public function records(Request $request): View
    {
        $stationId = $request->input('station_id');
        $from = $request->input('from');
        $to = $request->input('to');
        $sort = $request->input('sort', 'date');
        $dir = $request->input('dir', 'desc');

        $query = \App\Models\PickupPayout::with(['station', 'items.order', 'items.orderItem.product', 'items.orderItem.variant', 'reversedBy']);

        if ($stationId) $query->where('pickup_station_id', $stationId);
        if ($from) $query->whereDate('created_at', '>=', $from);
        if ($to) $query->whereDate('created_at', '<=', $to);

        if ($sort === 'station') {
            $query->orderBy('pickup_station_id', $dir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', $dir === 'asc' ? 'asc' : 'desc');
        }

        $payouts = $query->paginate(25)->withQueryString();
        $stations = PickupStation::orderBy('name')->get();

        return view('admin.payouts.records', compact('payouts', 'stations', 'stationId', 'from', 'to'));
    }

    /** Export payout records as CSV for current filters */
    public function export(Request $request): StreamedResponse
    {
        $stationId = $request->input('station_id');
        $from = $request->input('from');
        $to = $request->input('to');

        $query = \App\Models\PickupPayout::with(['station', 'items.order']);
        if ($stationId) $query->where('pickup_station_id', $stationId);
        if ($from) $query->whereDate('created_at', '>=', $from);
        if ($to) $query->whereDate('created_at', '<=', $to);

        $filename = 'pickup_payouts_' . now()->format('Ymd_His') . '.csv';

        $callback = function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Reference', 'Station', 'Amount', 'Created At', 'Note', 'Orders', 'Item Fees', 'Reversed By', 'Reversed At']);

            foreach ($query->orderByDesc('created_at')->cursor() as $p) {
                $orders = $p->items->pluck('order')
                    ->filter()
                    ->map(fn($o) => $o->reference ?? 'Order #' . $o->id)
                    ->implode('|');

                $fees = $p->items->map(fn($it) => (string) $it->fee_amount)->implode('|');

                $reversedBy = $p->reversedBy?->name ?? '';
                $reversedAt = $p->reversed_at?->toDateTimeString() ?? '';
                fputcsv($handle, [$p->reference, $p->station?->name, (string) $p->amount, $p->created_at->toDateTimeString(), $p->note, $orders, $fees, $reversedBy, $reversedAt]);
            }
            fclose($handle);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /** Reverse a payout: mark reversed and reset order item paid flags */
    public function reverse(Request $request, \App\Models\PickupPayout $pickupPayout)
    {
        if ($pickupPayout->is_reversed) return back()->with('error', 'Payout already reversed.');

        $affectedOrderIds = collect();

        foreach ($pickupPayout->items as $it) {
            if ($it->order_item_id) {
                \App\Models\OrderItem::where('id', $it->order_item_id)
                    ->update([
                        'pickup_station_fee_paid' => false,
                        'pickup_station_fee_paid_at' => null,
                    ]);
                $affectedOrderIds->push($it->order_id);
            }
        }

        // Recalculate pickup_station_fee_total for affected orders
        foreach ($affectedOrderIds->unique() as $orderId) {
            $order = \App\Models\Order::find($orderId);
            if ($order) {
                $this->pickupService->refreshOrderFeeTotal($order);
            }
        }

        $pickupPayout->update([
            'is_reversed' => true,
            'reversed_by' => Auth::id(),
            'reversed_at' => now(),
        ]);

        return back()->with('success', 'Payout reversed and item fees marked as unpaid.');
    }
}
