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
            $commission = $this->pickupService->calculateCommission($s->id);
            $payoutSummary = $this->pickupService->getPayoutSummary($s->id);

            return [
                'station' => $s,
                'total_earned' => $commission['total_commission'],
                'total_paid_out' => $payoutSummary['total_paid_out'],
                'balance_due' => $payoutSummary['balance_due'],
                'item_count' => $commission['item_count'],
            ];
        });

        return view('admin.payouts.index', compact('summary'));
    }

    /** Show details for a station — item-level commission breakdown */
    public function show(Request $request, PickupStation $pickupStation): View
    {
        $payoutSummary = $this->pickupService->getPayoutSummary($pickupStation->id);

        // Get all picked-up items for this station
        $pickedUpItems = OrderItem::whereHas('order', function ($q) use ($pickupStation) {
            $q->where('pickup_station_id', $pickupStation->id);
        })
        ->where('pickup_status', 'picked_up')
        ->with(['order', 'product', 'variant'])
        ->orderByDesc('pickup_status_changed_at')
        ->get();

        // Group by order
        $itemsByOrder = $pickedUpItems->groupBy('order_id')->map(function ($items, $orderId) {
            $order = $items->first()->order;
            $commission = $items->sum(fn($item) => $item->commission);
            return [
                'order' => $order,
                'items' => $items,
                'commission' => round($commission, 2),
            ];
        });

        return view('admin.payouts.show', compact('pickupStation', 'payoutSummary', 'itemsByOrder'));
    }

    /** Mark selected items as paid (bulk) — pays commission on picked_up items */
    public function markPaid(Request $request, PickupStation $pickupStation)
    {
        $data = $request->validate([
            'order_ids' => ['required', 'array'],
            'order_ids.*' => ['integer', 'exists:orders,id'],
            'note' => ['nullable', 'string'],
        ]);

        $orderIds = $data['order_ids'];

        $orders = \App\Models\Order::with('items')
            ->whereIn('id', $orderIds)
            ->where('pickup_station_id', $pickupStation->id)
            ->get();

        if ($orders->isEmpty()) {
            return back()->with('error', 'No matching orders found for this station.');
        }

        $total = 0;
        $itemsToMarkPaid = collect();

        foreach ($orders as $o) {
            $pickedUpItems = $o->items->where('pickup_status', 'picked_up');
            foreach ($pickedUpItems as $item) {
                $commission = $item->commission;
                $total += $commission;
                $itemsToMarkPaid->push($item);
            }
        }

        if ($total <= 0) {
            return back()->with('error', 'No picked-up items found for the selected orders.');
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

            // Refresh order-level fee total
            $this->pickupService->refreshOrderFeeTotal($item->order);
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

        $query = \App\Models\PickupPayout::with(['station', 'items.order', 'reversedBy']);

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
            if ($it->order_id) {
                \App\Models\OrderItem::where('order_id', $it->order_id)
                    ->where('pickup_status', 'picked_up')
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
