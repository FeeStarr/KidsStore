<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PickupStation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PickupStationController extends Controller
{
    public function index(): View
    {
        $stations = PickupStation::orderBy('name')->get();
        return view('admin.pickup_stations.index', compact('stations'));
    }

    public function create(): View
    {
        return view('admin.pickup_stations.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:120'],
            'address'      => ['required', 'string', 'max:500'],
            'city'         => ['nullable', 'string', 'max:100'],
            'state'        => ['nullable', 'string', 'max:100'],
            'phone'        => ['nullable', 'string', 'max:30'],
            'pickup_shipping_fee' => ['nullable','numeric','min:0'],
            'bank_name'            => ['nullable', 'string', 'max:120'],
            'bank_account_name'    => ['nullable', 'string', 'max:200'],
            'bank_account_number'  => ['nullable', 'string', 'max:60'],
            'bank_instructions'    => ['nullable', 'string', 'max:1000'],
            'instructions' => ['nullable', 'string', 'max:1000'],
            'fee_pct'      => ['nullable', 'numeric', 'min:0', 'max:100'],
            'access_pin'   => ['nullable', 'string', 'min:4', 'max:20'],
            'is_active'    => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['fee_pct']   = (float) ($data['fee_pct'] ?? 0);
        $data['pickup_shipping_fee'] = isset($data['pickup_shipping_fee']) ? (float) $data['pickup_shipping_fee'] : null;
        if (! empty($data['access_pin'])) {
            $data['access_pin'] = Hash::make($data['access_pin']);
        } else {
            unset($data['access_pin']);
        }

        $station = PickupStation::create($data);

        return redirect()->route('admin.pickup-stations.index')
            ->with('success', 'Pickup station created.');
    }

    public function edit(PickupStation $pickupStation): View
    {
        return view('admin.pickup_stations.edit', compact('pickupStation'));
    }

    public function update(Request $request, PickupStation $pickupStation): RedirectResponse
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:120'],
            'address'      => ['required', 'string', 'max:500'],
            'city'         => ['nullable', 'string', 'max:100'],
            'state'        => ['nullable', 'string', 'max:100'],
            'phone'        => ['nullable', 'string', 'max:30'],
            'bank_name'            => ['nullable', 'string', 'max:120'],
            'bank_account_name'    => ['nullable', 'string', 'max:200'],
            'bank_account_number'  => ['nullable', 'string', 'max:60'],
            'bank_instructions'    => ['nullable', 'string', 'max:1000'],
            'instructions' => ['nullable', 'string', 'max:1000'],
            'fee_pct'      => ['nullable', 'numeric', 'min:0', 'max:100'],
            'access_pin'   => ['nullable', 'string', 'min:4', 'max:20'],
            'is_active'    => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['fee_pct']   = (float) ($data['fee_pct'] ?? 0);
        $data['pickup_shipping_fee'] = isset($data['pickup_shipping_fee']) ? (float) $data['pickup_shipping_fee'] : null;
        if (! empty($data['access_pin'])) {
            $data['access_pin'] = Hash::make($data['access_pin']);
        } else {
            unset($data['access_pin']); // keep existing PIN
        }

        $pickupStation->update($data);

        // Bank accounts are managed globally; no station-specific accounts here.

        return redirect()->route('admin.pickup-stations.index')
            ->with('success', 'Pickup station updated.');
    }

    public function destroy(PickupStation $pickupStation): RedirectResponse
    {
        if ($pickupStation->orders()->exists()) {
            return back()->with('error', 'Cannot delete a station that has orders assigned to it.');
        }
        $pickupStation->delete();
        return redirect()->route('admin.pickup-stations.index')
            ->with('success', 'Pickup station deleted.');
    }

    /** Apply same pickup shipping fee across all stations */
    public function applyShippingFeeAll(Request $request)
    {
        $data = $request->validate([
            'fee' => ['required','numeric','min:0'],
        ]);

        \App\Models\PickupStation::query()->update(['pickup_shipping_fee' => (float) $data['fee']]);

        return redirect()->route('admin.pickup-stations.index')->with('success', 'Applied pickup shipping fee to all stations.');
    }

    /** Admin payout report for a specific station */
    public function payouts(Request $request, PickupStation $pickupStation): View
    {
        $period = $request->input('period', 'monthly');
        $from   = $request->input('from');
        $to     = $request->input('to');

        $query = Order::where('pickup_station_id', $pickupStation->id)
            ->where('delivery_method', 'pickup')
            ->where('status', 'delivered')
            ->whereNotNull('expected_delivery_date'); // proxy for "has a completed date"

        if ($from) $query->whereDate('updated_at', '>=', $from);
        if ($to)   $query->whereDate('updated_at', '<=', $to);

        $orders = $query->orderByDesc('updated_at')->get();

        // Aggregate by period
        $grouped = $orders->groupBy(function ($o) use ($period) {
            return match ($period) {
                'daily'   => $o->updated_at->format('Y-m-d'),
                'weekly'  => $o->updated_at->startOfWeek()->format('Y-m-d') . ' week',
                default   => $o->updated_at->format('Y-m'),
            };
        });

        $feePct    = (float) $pickupStation->fee_pct;
        $aggregate = $grouped->map(function ($rows) use ($feePct) {
            $totalSales = $rows->sum('grand_total');
            return [
                'orders'      => $rows->count(),
                'total_sales' => $totalSales,
                'fee_amount'  => round($totalSales * $feePct / 100, 2),
            ];
        });

        $grandTotal    = $orders->sum('grand_total');
        $totalFee      = round($grandTotal * $feePct / 100, 2);

        return view('admin.pickup_stations.payouts', compact(
            'pickupStation', 'aggregate', 'grandTotal', 'totalFee',
            'period', 'from', 'to', 'feePct'
        ));
    }
}
