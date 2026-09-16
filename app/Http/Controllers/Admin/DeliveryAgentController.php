<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAgent;
use App\Models\User;
use App\Notifications\DeliveryAgentCredentialsNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
            'phone'        => ['required', 'string', 'max:30'],
            'email'        => ['required', 'email', 'max:255', 'unique:users,email'],
            'notes'        => ['nullable', 'string', 'max:1000'],
            'is_active'    => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active', true);

        $tempPassword = Str::random(12);

        $user = User::create([
            'name'                => $data['name'],
            'email'               => $data['email'],
            'password'            => Hash::make($tempPassword),
            'phone'               => $data['phone'] ?? null,
            'role'                => User::ROLE_DELIVERY_AGENT,
            'is_active'           => $data['is_active'],
            'must_change_password'=> true,
        ]);

        $agent = DeliveryAgent::create([
            'name'         => $data['name'],
            'contact_name' => $data['contact_name'] ?? null,
            'phone'        => $data['phone'] ?? null,
            'email'        => $data['email'],
            'notes'        => $data['notes'] ?? null,
            'is_active'    => $data['is_active'],
            'account_number' => 'DA-' . str_pad($user->id, 6, '0', STR_PAD_LEFT),
            'user_id'      => $user->id,
        ]);

        $user->notify(new DeliveryAgentCredentialsNotification(
            $data['email'],
            $tempPassword,
            $agent->account_number,
        ));

        return redirect()->route('admin.delivery-agents.index')
            ->with('success', 'Delivery agent created.')
            ->with('temp_credentials', [
                'email'    => $data['email'],
                'password' => $tempPassword,
                'account'  => $agent->account_number,
            ]);
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
            'phone'        => ['required', 'string', 'max:30'],
            'email'        => ['required', 'email', 'max:255', 'unique:users,email,' . $deliveryAgent->user_id],
            'notes'        => ['nullable', 'string', 'max:1000'],
            'is_active'    => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active', true);

        $deliveryAgent->update($data);

        // Sync is_active to linked User
        if ($deliveryAgent->user) {
            $deliveryAgent->user->update([
                'is_active' => $data['is_active'],
            ]);

            // If deactivated, force logout on next request
            if (! $data['is_active'] && $deliveryAgent->user->must_change_password) {
                $deliveryAgent->user->update(['must_change_password' => false]);
            }
        }

        return redirect()->route('admin.delivery-agents.index')
            ->with('success', 'Delivery agent updated.');
    }

    public function destroy(DeliveryAgent $deliveryAgent): RedirectResponse
    {
        if ($deliveryAgent->orders()->exists()) {
            return back()->with('error', 'Cannot delete an agent that has orders. Deactivate it instead.');
        }

        // Unlink user before deleting
        if ($deliveryAgent->user) {
            $deliveryAgent->user->update(['role' => User::ROLE_CUSTOMER]);
            $deliveryAgent->update(['user_id' => null]);
        }

        $deliveryAgent->delete();

        return redirect()->route('admin.delivery-agents.index')
            ->with('success', 'Delivery agent deleted.');
    }
}
