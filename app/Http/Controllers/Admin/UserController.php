<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::orderBy('created_at', 'desc')->paginate(20);

        return view('admin.users.index', compact('users'));
    }

    public function create(): View
    {
        return view('admin.users.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', Rule::in(array_keys(User::roleOptions()))],
            'staff_type' => ['nullable', Rule::in(array_keys(User::staffTypes()))],
            'password' => ['required', 'string', 'min:8'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $roles = $data['roles'];
        $staffType = in_array(User::ROLE_STAFF, $roles, true) ? ($data['staff_type'] ?? null) : null;

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $roles[0],
            'staff_type' => $staffType,
            'password' => Hash::make($data['password']),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        $user->syncRoles($roles);

        return redirect()->route('admin.users.index')->with('success', 'User created successfully.');
    }

    public function show(User $user): View
    {
        $user->load(['profile', 'addresses', 'roles']);

        return view('admin.users.show', compact('user'));
    }

    public function edit(User $user): View
    {
        $user->load('roles');

        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', Rule::in(array_keys(User::roleOptions()))],
            'staff_type' => ['nullable', Rule::in(array_keys(User::staffTypes()))],
            'password' => ['nullable', 'string', 'min:8'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $roles = $data['roles'];
        $staffType = in_array(User::ROLE_STAFF, $roles, true) ? ($data['staff_type'] ?? null) : null;

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->role = $roles[0];
        $user->staff_type = $staffType;
        if (array_key_exists('is_active', $data)) {
            $user->is_active = (bool) $data['is_active'];
        }

        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();
        $user->syncRoles($roles);

        return redirect()->route('admin.users.index')->with('success', 'User updated successfully.');
    }

    public function assignRole(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', Rule::in(array_keys(User::roleOptions()))],
            'staff_type' => ['nullable', Rule::in(array_keys(User::staffTypes()))],
        ]);

        $roles = $data['roles'];
        $staffType = in_array(User::ROLE_STAFF, $roles, true) ? ($data['staff_type'] ?? null) : null;

        $user->role = $roles[0];
        $user->staff_type = $staffType;
        $user->save();
        $user->syncRoles($roles);

        return redirect()->back()->with('success', 'Role assigned');
    }

    public function toggleActive(User $user): RedirectResponse
    {
        $user->is_active = ! $user->is_active;
        $user->save();

        return redirect()->back()->with('success', 'User status updated');
    }

    public function updateProfile(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'avatar_url' => ['nullable', 'url', 'max:255'],
        ]);

        $user->update([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
        ]);

        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'bio' => $data['bio'] ?? null,
                'avatar_url' => $data['avatar_url'] ?? null,
            ]
        );

        return redirect()->back()->with('success', 'Profile updated.');
    }

    public function storeAddress(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:100'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'country' => ['nullable', 'string', 'max:100'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        if (!empty($data['is_default'])) {
            Address::where('user_id', $user->id)->update(['is_default' => false]);
        }

        $user->addresses()->create([
            'label' => $data['label'] ?? null,
            'line1' => $data['line1'],
            'line2' => $data['line2'] ?? null,
            'city' => $data['city'],
            'state' => $data['state'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'country' => $data['country'] ?? 'Nigeria',
            'is_default' => (bool) ($data['is_default'] ?? false),
        ]);

        return redirect()->back()->with('success', 'Address added.');
    }

    public function updateAddress(Request $request, User $user, Address $address): RedirectResponse
    {
        abort_unless((int) $address->user_id === (int) $user->id, 404);

        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:100'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'country' => ['nullable', 'string', 'max:100'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        if (!empty($data['is_default'])) {
            Address::where('user_id', $user->id)->update(['is_default' => false]);
        }

        $address->update([
            'label' => $data['label'] ?? null,
            'line1' => $data['line1'],
            'line2' => $data['line2'] ?? null,
            'city' => $data['city'],
            'state' => $data['state'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'country' => $data['country'] ?? 'Nigeria',
            'is_default' => (bool) ($data['is_default'] ?? $address->is_default),
        ]);

        return redirect()->back()->with('success', 'Address updated.');
    }

    public function destroyAddress(User $user, Address $address): RedirectResponse
    {
        abort_unless((int) $address->user_id === (int) $user->id, 404);

        $wasDefault = (bool) $address->is_default;
        $address->delete();

        if ($wasDefault) {
            $next = Address::where('user_id', $user->id)->first();
            if ($next) {
                $next->update(['is_default' => true]);
            }
        }

        return redirect()->back()->with('success', 'Address removed.');
    }

    public function setDefaultAddress(User $user, Address $address): RedirectResponse
    {
        abort_unless((int) $address->user_id === (int) $user->id, 404);

        Address::where('user_id', $user->id)->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        return redirect()->back()->with('success', 'Default address updated.');
    }
}
