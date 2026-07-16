<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PickupStation;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PickupPayoutController extends Controller
{
    /** Show aggregated amounts per station for a period */
    public function index(Request $request): View
    {
        $period = $request->input('period', 'daily');
        $date = $request->input('date', now()->toDateString());
        $from = $request->input('from');
        $to = $request->input('to');

        $stations = PickupStation::orderBy('name')->get();

        // For each station compute total pickup fees within the period (order-level percent)
        $summary = $stations->map(function ($s) use ($period, $date, $from, $to) {
            $query = \App\Models\Order::query()
                ->where('pickup_station_id', $s->id)
                ->where('delivery_method', 'pickup');

            if ($from) {
                $query->whereDate('order_date', '>=', $from);
            }
            if ($to) {
                $query->whereDate('order_date', '<=', $to);
            }

            if (! $from && ! $to) {
                if ($period === 'all') {
                    // no date filter
                } elseif ($period === 'daily') {
                    $query->whereDate('order_date', $date);
                } elseif ($period === 'weekly') {
                    $d = \Carbon\Carbon::parse($date);
                    $query->whereBetween('order_date', [$d->startOfWeek()->toDateString(), $d->endOfWeek()->toDateString()]);
                } else {
                    $d = \Carbon\Carbon::parse($date);
                    $query->whereBetween('order_date', [$d->startOfMonth()->toDateString(), $d->endOfMonth()->toDateString()]);
                }
            }

            $orders = $query->get();

            $totalDue = $orders->sum(function ($o) use ($s) {
                return round($o->grand_total * ((float)$s->fee_pct) / 100, 2);
            });

            $pendingOrders = $orders->filter(function ($o) {
                return $o->items->contains(fn($it) => ! $it->pickup_station_fee_paid);
            });

            $totalPending = $pendingOrders->sum(function ($o) use ($s) {
                return round($o->grand_total * ((float)$s->fee_pct) / 100, 2);
            });
            $countPending = $pendingOrders->count();

            return [
                'station' => $s,
                'total_due' => (float) $totalDue,
                'total_pending' => (float) $totalPending,
                'count_pending' => $countPending,
            ];
        });

        return view('admin.payouts.index', compact('summary', 'period', 'date', 'from', 'to'));
    }

    /** Show details for a station and period */
    public function show(Request $request, PickupStation $pickupStation): View
    {
        $period = $request->input('period', 'daily');
        $date = $request->input('date', now()->toDateString());
        $from = $request->input('from');
        $to = $request->input('to');

        // Fetch orders for the station in the period that have unpaid pickup fees
        $query = \App\Models\Order::with('items')
            ->where('pickup_station_id', $pickupStation->id)
            ->where('delivery_method', 'pickup');

        if ($from) {
            $query->whereDate('order_date', '>=', $from);
        }
        if ($to) {
            $query->whereDate('order_date', '<=', $to);
        }

        if (! $from && ! $to) {
            if ($period === 'daily') {
                $query->whereDate('order_date', $date);
            } elseif ($period === 'weekly') {
                $d = \Carbon\Carbon::parse($date);
                $query->whereBetween('order_date', [$d->startOfWeek()->toDateString(), $d->endOfWeek()->toDateString()]);
            } elseif ($period === 'monthly') {
                $d = \Carbon\Carbon::parse($date);
                $query->whereBetween('order_date', [$d->startOfMonth()->toDateString(), $d->endOfMonth()->toDateString()]);
            }
        }

        // only orders that have at least one unpaid order_item
        $orders = $query->whereHas('items', function ($q) {
            $q->where('pickup_station_fee_paid', false);
        })->orderByDesc('order_date')->get();

        // compute fee per order using station fee_pct
        $feePct = (float) $pickupStation->fee_pct;

        return view('admin.payouts.show', compact('pickupStation', 'period', 'date', 'orders', 'feePct'));
    }

    /** Mark selected items as paid (bulk) */
    public function markPaid(Request $request, PickupStation $pickupStation)
    {
        $orderIds = $request->input('order_ids', []);
        if (empty($orderIds)) return back()->with('error', 'No orders selected.');

        $data = $request->validate([
            'order_ids' => ['required','array'],
            'order_ids.*' => ['integer','exists:orders,id'],
            'note' => ['nullable','string'],
        ]);

        $orderIds = $data['order_ids'];

        $orders = \App\Models\Order::with('items')
            ->whereIn('id', $orderIds)
            ->where('pickup_station_id', $pickupStation->id)
            ->get();

        $total = 0;
        foreach ($orders as $o) {
            $fee = round($o->grand_total * ((float)$pickupStation->fee_pct) / 100, 2);
            $total += $fee;
        }

        $payout = \App\Models\PickupPayout::create([
            'pickup_station_id' => $pickupStation->id,
            'amount' => $total,
            'created_by' => auth()->id(),
            'reference' => 'PP-'.Str::upper(substr((string) Str::uuid(), 0, 8)),
            'note' => $data['note'] ?? null,
        ]);

        foreach ($orders as $o) {
            $fee = round($o->grand_total * ((float)$pickupStation->fee_pct) / 100, 2);
            \App\Models\PickupPayoutItem::create([
                'pickup_payout_id' => $payout->id,
                'order_id' => $o->id,
                'fee_amount' => $fee,
            ]);

            // mark all items for the order as paid
            \App\Models\OrderItem::where('order_id', $o->id)->update([
                'pickup_station_fee_paid' => true,
                'pickup_station_fee_paid_at' => now(),
            ]);
        }

        if ($orders->isEmpty()) return back()->with('error', 'No matching orders found for this station.');

        return back()->with('success', 'Marked selected orders as paid and created payout record.');
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

        // sorting
        if ($sort === 'station') {
            $query->orderBy('pickup_station_id', $dir === 'asc' ? 'asc' : 'desc');
        } else {
            // default: date
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

        if ($sort === 'station') {
            $query->orderBy('pickup_station_id', $dir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', $dir === 'asc' ? 'asc' : 'desc');
        }

        $filename = 'pickup_payouts_'.now()->format('Ymd_His').'.csv';

        $callback = function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Reference','Station','Amount','Created At','Note','Orders','Order Fees','Reversed By','Reversed At']);

            foreach ($query->orderByDesc('created_at')->cursor() as $p) {
                $orders = $p->items->pluck('order')
                    ->filter()
                    ->map(fn($o) => $o->reference ?? 'Order #'.$o->id)
                    ->implode('|');

                $fees = $p->items->map(fn($it)=> (string)$it->fee_amount)->implode('|');

                $reversedBy = $p->reversedBy?->name ?? '';
                $reversedAt = $p->reversed_at?->toDateTimeString() ?? '';
                fputcsv($handle, [$p->reference, $p->station?->name, (string)$p->amount, $p->created_at->toDateTimeString(), $p->note, $orders, $fees, $reversedBy, $reversedAt]);
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

        // For each payout item, if it references an order, reset that order's items
        foreach ($pickupPayout->items as $it) {
            if ($it->order_id) {
                \App\Models\OrderItem::where('order_id', $it->order_id)->update([
                    'pickup_station_fee_paid' => false,
                    'pickup_station_fee_paid_at' => null,
                ]);
            }
        }

        $pickupPayout->update([
            'is_reversed' => true,
            'reversed_by' => Auth::id(),
            'reversed_at' => now(),
        ]);

        return back()->with('success', 'Payout reversed and order fees marked as unpaid.');
    }
}
