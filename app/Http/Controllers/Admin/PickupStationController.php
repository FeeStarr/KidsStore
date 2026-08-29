<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PickupStation;
use App\Models\PickupStationBankAccount;
use App\Services\PickupStationService;
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
            'email'        => ['required', 'email', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:1000'],
            'fee_pct'      => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pickup_shipping_fee' => ['nullable', 'numeric', 'min:0'],
            'access_pin'   => ['nullable', 'string', 'min:6', 'max:20', 'regex:/^[a-zA-Z0-9]+$/'],
            'is_active'    => ['nullable', 'boolean'],
            'is_available' => ['nullable', 'boolean'],
            'unavailability_reason' => ['nullable', 'string', 'max:500'],
        ]);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['is_available'] = $request->boolean('is_available', true);
        $data['fee_pct']   = (float) ($data['fee_pct'] ?? 0);
        $data['pickup_shipping_fee'] = isset($data['pickup_shipping_fee']) ? (float) $data['pickup_shipping_fee'] : null;
        if (! empty($data['access_pin'])) {
            $data['access_pin'] = Hash::make($data['access_pin']);
        } else {
            unset($data['access_pin']);
        }

        $station = PickupStation::create($data);
        $this->syncBankAccounts($station, $request);

        return redirect()->route('admin.pickup-stations.index')
            ->with('success', 'Pickup station created.');
    }

    public function edit(PickupStation $pickupStation): View
    {
        $pickupStation->load('bankAccounts');
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
            'email'        => ['required', 'email', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:1000'],
            'fee_pct'      => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pickup_shipping_fee' => ['nullable', 'numeric', 'min:0'],
            'access_pin'   => ['nullable', 'string', 'min:6', 'max:20', 'regex:/^[a-zA-Z0-9]+$/'],
            'is_active'    => ['nullable', 'boolean'],
            'is_available' => ['nullable', 'boolean'],
            'unavailability_reason' => ['nullable', 'string', 'max:500'],
        ]);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['is_available'] = $request->boolean('is_available', true);
        $data['fee_pct']   = (float) ($data['fee_pct'] ?? 0);
        $data['pickup_shipping_fee'] = isset($data['pickup_shipping_fee']) ? (float) $data['pickup_shipping_fee'] : null;
        if (! empty($data['access_pin'])) {
            $data['access_pin'] = Hash::make($data['access_pin']);
        } else {
            unset($data['access_pin']); // keep existing PIN
        }

        $pickupStation->update($data);
        $this->syncBankAccounts($pickupStation, $request);

        return redirect()->route('admin.pickup-stations.index')
            ->with('success', 'Pickup station updated.');
    }

    protected function syncBankAccounts(PickupStation $station, Request $request): void
    {
        $incoming = $request->input('accounts', []);
        $defaultIdx = $request->input('default_account');
        $existingIds = [];

        foreach ($incoming as $idx => $row) {
            if (empty($row['bank_name']) && empty($row['bank_account_name']) && empty($row['bank_account_number'])) {
                continue;
            }
            $attrs = [
                'pickup_station_id'  => $station->id,
                'bank_name'          => $row['bank_name'] ?? null,
                'bank_account_name'  => $row['bank_account_name'] ?? null,
                'bank_account_number'=> $row['bank_account_number'] ?? null,
                'instructions'       => $row['instructions'] ?? null,
                'is_default'         => ((string) $idx === (string) $defaultIdx),
                'is_active'          => true,
            ];

            if (! empty($row['id'])) {
                PickupStationBankAccount::where('id', $row['id'])
                    ->where('pickup_station_id', $station->id)
                    ->update($attrs);
                $existingIds[] = $row['id'];
            } else {
                $acct = PickupStationBankAccount::create($attrs);
                $existingIds[] = $acct->id;
            }
        }

        // Delete removed accounts
        $station->bankAccounts()->whereNotIn('id', $existingIds)->delete();

        // Ensure only one default
        if ($defaultIdx !== null) {
            $station->bankAccounts()->where('is_default', true)->update(['is_default' => false]);
        }
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

    /** Apply a shipping fee to all pickup stations */
    public function applyShippingFeeAll(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'fee' => ['required', 'numeric', 'min:0'],
        ]);

        PickupStation::query()->update(['pickup_shipping_fee' => (float) $data['fee']]);

        return back()->with('success', 'Shipping fee of ₦' . number_format((float) $data['fee'], 2) . ' applied to all pickup stations.');
    }

    /** Redirect to the accurate payout ledger page */
    public function payouts(Request $request, PickupStation $pickupStation): \Illuminate\Http\RedirectResponse
    {
        return redirect()->route('admin.pickup-payouts.show', $pickupStation);
    }

    /** Toggle station availability */
    public function toggleAvailability(PickupStation $pickupStation): RedirectResponse
    {
        $pickupStation->update([
            'is_available' => ! $pickupStation->is_available,
            'unavailability_reason' => $pickupStation->is_available ? null : null,
        ]);

        $status = $pickupStation->is_available ? 'available' : 'unavailable';
        return back()->with('success', "Station marked as {$status}.");
    }

    /** Set station unavailable with reason */
    public function setUnavailable(Request $request, PickupStation $pickupStation): RedirectResponse
    {
        $data = $request->validate([
            'unavailability_reason' => ['required', 'string', 'max:500'],
        ]);

        $pickupStation->update([
            'is_available' => false,
            'unavailability_reason' => $data['unavailability_reason'],
        ]);

        return back()->with('success', 'Station marked as unavailable.');
    }

    /** Reassign an order to a different station */
    public function reassignOrder(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'new_station_id' => ['required', 'exists:pickup_stations,id'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $service = app(PickupStationService::class);
            $service->reassignOrder($order, $data['new_station_id'], $data['reason'] ?? null);
            return back()->with('success', 'Order reassigned successfully.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /** View station items by status */
    public function items(PickupStation $pickupStation): View
    {
        $service = app(PickupStationService::class);
        $itemsByStatus = $service->getItemsByStatus($pickupStation->id);
        $commission = $service->calculateCommission($pickupStation->id);

        return view('admin.pickup_stations.items', compact('pickupStation', 'itemsByStatus', 'commission'));
    }

    /** DataTable endpoint for station items */
    public function itemsData(Request $request, PickupStation $pickupStation)
    {
        $q = \App\Models\OrderItem::whereHas('order', function ($q) use ($pickupStation) {
            $q->where('pickup_station_id', $pickupStation->id);
        })
        ->with(['order.customer', 'product', 'variant']);

        // Filter by status
        $status = $request->input('status');
        if ($status && $status !== 'all') {
            $q->where('pickup_status', $status);
        }

        // Search
        $search = $request->input('search.value');
        if ($search) {
            $q->where(function ($sub) use ($search) {
                $sub->whereHas('product', fn($p) => $p->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('order', fn($o) => $o->where('reference', 'like', "%{$search}%"))
                    ->orWhereHas('order.customer', fn($c) => $c->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('variant', fn($v) => $v->where('options_label', 'like', "%{$search}%"));
            });
        }

        // Date filter
        $from = $request->input('from');
        $to = $request->input('to');
        if ($from) $q->whereDate('created_at', '>=', $from);
        if ($to) $q->whereDate('created_at', '<=', $to);

        $recordsTotal = (clone $q)->count();
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 25);

        $items = $q->orderByDesc('pickup_status_changed_at')->offset($start)->limit($length)->get();

        $statusColors = [
            'pending' => 'secondary',
            'received' => 'info',
            'ready for pickup' => 'success',
            'picked_up' => 'primary',
        ];

        $data = $items->map(function ($item) use ($statusColors) {
            $status = $item->pickup_status;
            $color = $statusColors[$status] ?? 'secondary';
            return [
                'order_reference' => $item->order?->reference ? '<a href="' . route('admin.orders.show', $item->order_id) . '">' . e($item->order->reference) . '</a>' : '-',
                'customer' => e($item->order?->customer?->name ?? '-'),
                'product' => e($item->product?->name ?? '-'),
                'variant' => e($item->variant?->options_label ?? '-'),
                'quantity' => $item->quantity,
                'unit_price' => '₦' . number_format($item->unit_price, 2),
                'commission' => $status === 'picked_up' ? '<span class="text-success fw-bold">₦' . number_format($item->commission, 2) . '</span>' : '-',
                'status' => '<span class="badge bg-' . $color . '">' . e(ucwords(str_replace('_', ' ', $status))) . '</span>',
            ];
        })->values();

        return response()->json([
            'draw' => (int) $request->input('draw', 0),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsTotal,
            'data' => $data,
        ]);
    }
}
