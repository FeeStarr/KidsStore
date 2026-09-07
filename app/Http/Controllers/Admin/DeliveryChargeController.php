<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAgent;
use App\Models\DeliveryCharge;
use App\Models\DeliveryLocation;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;

class DeliveryChargeController extends Controller
{
    public function index(): View
    {
        $charges = DeliveryCharge::with(['agent', 'location'])
            ->orderBy('delivery_agent_id')
            ->orderBy('delivery_location_id')
            ->get();
        $agents = DeliveryAgent::where('is_active', true)->orderBy('name')->get();
        $locations = DeliveryLocation::where('is_active', true)->orderBy('name')->get();

        return view('admin.delivery_charges.index', compact('charges', 'agents', 'locations'));
    }

    public function create(): View
    {
        $agents = DeliveryAgent::where('is_active', true)->orderBy('name')->get();
        $locations = DeliveryLocation::where('is_active', true)->orderBy('name')->get();

        return view('admin.delivery_charges.create', compact('agents', 'locations'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'delivery_agent_id'     => ['required', 'exists:delivery_agents,id'],
            'delivery_location_id'  => ['required', 'exists:delivery_locations,id'],
            'amount'                => ['required', 'numeric', 'min:0'],
            'is_active'             => ['nullable', 'boolean'],
            'effective_from'        => ['nullable', 'date'],
        ]);
        $data['is_active'] = $request->boolean('is_active', true);

        $existing = DeliveryCharge::where('delivery_agent_id', $data['delivery_agent_id'])
            ->where('delivery_location_id', $data['delivery_location_id'])
            ->first();

        if ($existing) {
            return back()->withInput()
                ->with('error', 'A charge already exists for this agent and location combination. Edit the existing one.');
        }

        DeliveryCharge::create($data);

        return redirect()->route('admin.delivery-charges.index')
            ->with('success', 'Delivery charge created.');
    }

    public function edit(DeliveryCharge $deliveryCharge): View
    {
        $agents = DeliveryAgent::where('is_active', true)->orderBy('name')->get();
        $locations = DeliveryLocation::where('is_active', true)->orderBy('name')->get();

        return view('admin.delivery_charges.edit', compact('deliveryCharge', 'agents', 'locations'));
    }

    public function update(Request $request, DeliveryCharge $deliveryCharge): RedirectResponse
    {
        $data = $request->validate([
            'delivery_agent_id'     => ['required', 'exists:delivery_agents,id'],
            'delivery_location_id'  => ['required', 'exists:delivery_locations,id'],
            'amount'                => ['required', 'numeric', 'min:0'],
            'is_active'             => ['nullable', 'boolean'],
            'effective_from'        => ['nullable', 'date'],
        ]);
        $data['is_active'] = $request->boolean('is_active', true);

        $duplicate = DeliveryCharge::where('delivery_agent_id', $data['delivery_agent_id'])
            ->where('delivery_location_id', $data['delivery_location_id'])
            ->where('id', '!=', $deliveryCharge->id)
            ->first();

        if ($duplicate) {
            return back()->withInput()
                ->with('error', 'Another charge already exists for this agent and location combination.');
        }

        $deliveryCharge->update($data);

        return redirect()->route('admin.delivery-charges.index')
            ->with('success', 'Delivery charge updated.');
    }

    public function destroy(DeliveryCharge $deliveryCharge): RedirectResponse
    {
        $used = Order::where('delivery_agent_id', $deliveryCharge->delivery_agent_id)
            ->where('delivery_location_id', $deliveryCharge->delivery_location_id)
            ->exists();

        if ($used) {
            return back()->with('error', 'Cannot delete a charge that has been used by orders. Deactivate it instead.');
        }
        $deliveryCharge->delete();

        return redirect()->route('admin.delivery-charges.index')
            ->with('success', 'Delivery charge deleted.');
    }
}
