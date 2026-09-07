<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;

class DeliveryLocationController extends Controller
{
    public function index(): View
    {
        $locations = DeliveryLocation::withCount('charges')->orderBy('name')->get();
        return view('admin.delivery_locations.index', compact('locations'));
    }

    public function create(): View
    {
        return view('admin.delivery_locations.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:120'],
            'state'       => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active'   => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active', true);

        DeliveryLocation::create($data);

        return redirect()->route('admin.delivery-locations.index')
            ->with('success', 'Delivery location created.');
    }

    public function edit(DeliveryLocation $deliveryLocation): View
    {
        return view('admin.delivery_locations.edit', compact('deliveryLocation'));
    }

    public function update(Request $request, DeliveryLocation $deliveryLocation): RedirectResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:120'],
            'state'       => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active'   => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active', true);

        $deliveryLocation->update($data);

        return redirect()->route('admin.delivery-locations.index')
            ->with('success', 'Delivery location updated.');
    }

    public function destroy(DeliveryLocation $deliveryLocation): RedirectResponse
    {
        if ($deliveryLocation->orders()->exists()) {
            return back()->with('error', 'Cannot delete a location that has orders. Deactivate it instead.');
        }
        $deliveryLocation->delete();

        return redirect()->route('admin.delivery-locations.index')
            ->with('success', 'Delivery location deleted.');
    }
}
