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
        $users = User::with('roles')->orderBy('created_at', 'desc')->limit(2000)->get();

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
        $currentUser = auth()->user();

        // Security: only superadmin can edit another superadmin or change someone's role to superadmin
        $requestedRoles = $request->input('roles', []);
        $isAssigningSuperAdmin = in_array(User::ROLE_SUPERADMIN, $requestedRoles);

        if ($user->isSuperAdmin() && !$currentUser->isSuperAdmin()) {
            return redirect()->back()->withErrors(['roles' => 'Only Super Admins can edit other Super Admins.']);
        }

        if ($isAssigningSuperAdmin && !$currentUser->isSuperAdmin()) {
            return redirect()->back()->withErrors(['roles' => 'Only Super Admins can assign the Super Admin role.']);
        }

        // Prevent self-lockout: cannot deactivate yourself or change your own role to something non-admin
        if ($currentUser->id === $user->id) {
            if ($request->has('is_active') && !$request->boolean('is_active')) {
                return redirect()->back()->withErrors(['is_active' => 'You cannot deactivate your own account.']);
            }

            $isAdminRole = false;
            foreach ($requestedRoles as $role) {
                if ($role === User::ROLE_ADMIN || $role === User::ROLE_SUPERADMIN) {
                    $isAdminRole = true;
                    break;
                }
            }
            if (!$isAdminRole) {
                return redirect()->back()->withErrors(['roles' => 'You cannot remove your own admin role.']);
            }
        }

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
        $currentUser = auth()->user();

        $data = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', Rule::in(array_keys(User::roleOptions()))],
            'staff_type' => ['nullable', Rule::in(array_keys(User::staffTypes()))],
        ]);

        $roles = $data['roles'];

        // Security: only superadmin can assign superadmin role or modify a superadmin
        if (($user->isSuperAdmin() || in_array(User::ROLE_SUPERADMIN, $roles)) && !$currentUser->isSuperAdmin()) {
            return redirect()->back()->withErrors(['roles' => 'Only Super Admins can manage Super Admin roles.']);
        }

        // Prevent self-demotion
        if ($currentUser->id === $user->id) {
            $isAdminRole = false;
            foreach ($roles as $role) {
                if ($role === User::ROLE_ADMIN || $role === User::ROLE_SUPERADMIN) {
                    $isAdminRole = true;
                    break;
                }
            }
            if (!$isAdminRole) {
                return redirect()->back()->withErrors(['roles' => 'You cannot remove your own admin role.']);
            }
        }

        $staffType = in_array(User::ROLE_STAFF, $roles, true) ? ($data['staff_type'] ?? null) : null;

        $user->role = $roles[0];
        $user->staff_type = $staffType;
        $user->save();
        $user->syncRoles($roles);

        return redirect()->back()->with('success', 'Role assigned');
    }

    public function toggleActive(User $user): RedirectResponse
    {
        if (auth()->id() === $user->id) {
            return redirect()->back()->withErrors(['error' => 'You cannot deactivate your own account.']);
        }

        // Only superadmin can deactivate other admins/superadmins (optional but safer)
        if ($user->isAdmin() && !auth()->user()->isSuperAdmin()) {
            return redirect()->back()->withErrors(['error' => 'Only Super Admins can deactivate other Admin accounts.']);
        }

        $user->is_active = ! $user->is_active;
        $user->save();

        $state = $user->is_active ? 'activated' : 'deactivated';
        return redirect()->back()->with('success', "User account {$state}.");
    }

    public function toggle2FA(User $user): RedirectResponse
    {
        $user->two_factor_enabled = ! $user->two_factor_enabled;
        // If disabling, clear any active 2FA code too
        if (! $user->two_factor_enabled) {
            $user->two_factor_code       = null;
            $user->two_factor_expires_at = null;
        }
        $user->save();

        $state = $user->two_factor_enabled ? 'enabled' : 'disabled';
        return redirect()->back()->with('success', "Two-factor authentication {$state} for {$user->name}.");
    }

    public function generate2FABackup(User $user): RedirectResponse
    {
        $plain = $user->generateBackupCode();

        // Flash the plaintext once - it is NEVER stored in plaintext anywhere
        return redirect()->back()->with('backup_code', $plain)
            ->with('backup_code_user', $user->name);
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
