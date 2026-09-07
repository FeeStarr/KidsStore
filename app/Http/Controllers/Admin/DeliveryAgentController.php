<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAgent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;

class DeliveryAgentController extends Controller
{
    public function index(): View
    {
        $agents = DeliveryAgent::withCount('charges')->orderBy('name')->get();
        return view('admin.delivery_agents.index', compact('agents'));
    }

    public function create(): View
    {
        return view('admin.delivery_agents.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:120'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'phone'        => ['nullable', 'string', 'max:30'],
            'email'        => ['nullable', 'email', 'max:255'],
            'notes'        => ['nullable', 'string', 'max:1000'],
            'is_active'    => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active', true);

        DeliveryAgent::create($data);

        return redirect()->route('admin.delivery-agents.index')
            ->with('success', 'Delivery agent created.');
    }

    public function edit(DeliveryAgent $deliveryAgent): View
    {
        return view('admin.delivery_agents.edit', compact('deliveryAgent'));
    }

    public function update(Request $request, DeliveryAgent $deliveryAgent): RedirectResponse
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:120'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'phone'        => ['nullable', 'string', 'max:30'],
            'email'        => ['nullable', 'email', 'max:255'],
            'notes'        => ['nullable', 'string', 'max:1000'],
            'is_active'    => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active', true);

        $deliveryAgent->update($data);

        return redirect()->route('admin.delivery-agents.index')
            ->with('success', 'Delivery agent updated.');
    }

    public function destroy(DeliveryAgent $deliveryAgent): RedirectResponse
    {
        if ($deliveryAgent->orders()->exists()) {
            return back()->with('error', 'Cannot delete an agent that has orders. Deactivate it instead.');
        }
        $deliveryAgent->delete();

        return redirect()->route('admin.delivery-agents.index')
            ->with('success', 'Delivery agent deleted.');
    }
}
