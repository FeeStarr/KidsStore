@extends('layouts.admin', ['title' => 'User Details'])

@section('content')
@php
    $roles = \App\Models\User::roleOptions();
    $staffTypes = \App\Models\User::staffTypes();
@endphp

@if(session('backup_code'))
<div class="alert alert-warning border-2 border-warning shadow-sm mb-3" role="alert">
    <h5 class="alert-heading"><i class="bi bi-key-fill me-2"></i>Backup Code for {{ session('backup_code_user') }}</h5>
    <p class="mb-2">Give this code to the user. <strong>It will only be shown once and cannot be retrieved again.</strong></p>
    <div class="d-flex align-items-center gap-3">
        <span class="fs-3 fw-bold font-monospace letter-spacing-2 bg-white border rounded px-3 py-1">
            {{ session('backup_code') }}
        </span>
        <button type="button" class="btn btn-sm btn-outline-secondary"
                onclick="navigator.clipboard.writeText('{{ session('backup_code') }}');this.innerHTML='<i class=\'bi bi-check\' ></i> Copied'">
            <i class="bi bi-clipboard me-1"></i>Copy
        </button>
    </div>
    <hr class="my-2">
    <small class="text-muted">
        The user can enter this code instead of their emailed 2FA code if they can't access their email.
        It is <strong>single-use</strong> - it expires after one successful login.
    </small>
</div>
@endif

<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h3 class="mb-0"><i class="bi bi-person"></i> User Details</h3>
        <p class="text-muted mb-0">Review profile, role, and activity status.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-outline-secondary btn-sm">Edit</a>
        <form method="post" action="{{ route('admin.users.toggle-active', $user) }}">
            @csrf
            <button class="btn btn-sm {{ $user->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}">
                {{ $user->is_active ? 'Deactivate' : 'Activate' }}
            </button>
        </form>
        <form method="post" action="{{ route('admin.users.toggle-2fa', $user) }}">
            @csrf
            <button class="btn btn-sm {{ $user->two_factor_enabled ? 'btn-outline-warning' : 'btn-outline-primary' }}"
                    title="{{ $user->two_factor_enabled ? 'Click to disable 2FA' : 'Click to enable 2FA' }}">
                <i class="bi bi-shield{{ $user->two_factor_enabled ? '-fill' : '' }} me-1"></i>
                2FA {{ $user->two_factor_enabled ? 'ON' : 'OFF' }}
            </button>
        </form>
        @if($user->two_factor_enabled)
        <form method="post" action="{{ route('admin.users.generate-backup-code', $user) }}"
              onsubmit="return confirm('Generate a new backup code for {{ addslashes($user->name) }}? Any existing backup code will be invalidated.')">
            @csrf
            <button class="btn btn-sm btn-outline-info" title="Generate a one-time backup/recovery code">
                <i class="bi bi-key me-1"></i>{{ $user->hasBackupCode() ? 'Regenerate' : 'Generate' }} Backup Code
            </button>
        </form>
        @endif
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h5 class="card-title mb-3">Account</h5>
                <dl class="row mb-0">
                    <dt class="col-sm-4">Name</dt>
                    <dd class="col-sm-8">{{ $user->name }}</dd>

                    <dt class="col-sm-4">Email</dt>
                    <dd class="col-sm-8">{{ $user->email }}</dd>

                    <dt class="col-sm-4">Roles</dt>
                    <dd class="col-sm-8">
                        @php $assignedRoles = $user->roles->pluck('name')->all() ?: [$user->role]; @endphp
                        {{ implode(', ', array_map(fn($role) => $roles[$role] ?? $role, $assignedRoles)) }}
                    </dd>

                    <dt class="col-sm-4">Staff Type</dt>
                    <dd class="col-sm-8">{{ $staffTypes[$user->staff_type] ?? ($user->staff_type ?? 'Not set') }}</dd>

                    <dt class="col-sm-4">Status</dt>
                    <dd class="col-sm-8">
                        @if($user->is_active)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </dd>

                    <dt class="col-sm-4">Two-Factor Auth</dt>
                    <dd class="col-sm-8">
                        @if($user->two_factor_enabled)
                            <span class="badge bg-primary"><i class="bi bi-shield-fill me-1"></i>Enabled</span>
                        @else
                            <span class="badge bg-secondary"><i class="bi bi-shield me-1"></i>Disabled</span>
                        @endif
                    </dd>

                    <dt class="col-sm-4">Backup Code</dt>
                    <dd class="col-sm-8">
                        @if($user->hasBackupCode())
                            <span class="badge bg-success"><i class="bi bi-key me-1"></i>Active (stored hashed)</span>
                        @else
                            <span class="badge bg-light text-muted border">None</span>
                        @endif
                    </dd>

                    <dt class="col-sm-4">Joined</dt>
                    <dd class="col-sm-8">{{ $user->created_at->format('M d, Y h:ia') }}</dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-body">
                <h5 class="card-title mb-3">Assign Role</h5>
                <form method="post" action="{{ route('admin.users.assign-role', $user) }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Roles</label>
                        <select name="roles[]" class="form-select" multiple required size="5">
                            @php $selectedRoles = old('roles', $user->roles->pluck('name')->all() ?: [$user->role]); @endphp
                            @foreach($roles as $value => $label)
                                <option value="{{ $value }}" {{ in_array($value, $selectedRoles, true) ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Hold Ctrl/Cmd or use multiple selections to assign more than one role.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Staff Type (optional)</label>
                        <select name="staff_type" class="form-select">
                            <option value="">No staff type</option>
                            @foreach($staffTypes as $value => $label)
                                <option value="{{ $value }}" {{ $user->staff_type === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Only used when role is Staff.</div>
                    </div>
                    <button class="btn btn-primary">Update Role</button>
                </form>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h5 class="card-title mb-2">Quick Links</h5>
                <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary btn-sm">Back to Users</a>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h5 class="card-title mb-3">Profile Details</h5>
                <form method="post" action="{{ route('admin.users.profile.update', $user) }}">
                    @csrf
                    @method('put')
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}" required>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" value="{{ old('phone', $user->phone) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Primary Address</label>
                            <input type="text" name="address" class="form-control" value="{{ old('address', $user->address) }}">
                        </div>
                    </div>
                    <div class="row g-2 mt-1">
                        <div class="col-md-6">
                            <label class="form-label">First Name</label>
                            <input type="text" name="first_name" class="form-control" value="{{ old('first_name', optional($user->profile)->first_name) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="last_name" class="form-control" value="{{ old('last_name', optional($user->profile)->last_name) }}">
                        </div>
                    </div>
                    <div class="mb-3 mt-2">
                        <label class="form-label">Bio</label>
                        <textarea name="bio" rows="3" class="form-control">{{ old('bio', optional($user->profile)->bio) }}</textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Avatar URL</label>
                        <input type="url" name="avatar_url" class="form-control" value="{{ old('avatar_url', optional($user->profile)->avatar_url) }}">
                    </div>
                    <button class="btn btn-primary">Save Profile</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-body">
                <h5 class="card-title mb-3">Addresses</h5>
                @forelse($user->addresses as $address)
                    <div class="border rounded p-3 mb-3">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="fw-semibold">{{ $address->label ?: 'Address' }}</div>
                                <div>{{ $address->line1 }}</div>
                                @if($address->line2)<div>{{ $address->line2 }}</div>@endif
                                <div>{{ $address->city }}{{ $address->state ? ', '.$address->state : '' }}</div>
                                <div>{{ $address->country }}{{ $address->postal_code ? ' • '.$address->postal_code : '' }}</div>
                                @if($address->is_default)
                                    <span class="badge bg-success mt-2">Default</span>
                                @endif
                            </div>
                            <div class="d-flex flex-column gap-2">
                                <form method="post" action="{{ route('admin.users.addresses.default', [$user, $address]) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-primary">Make Default</button>
                                </form>
                                <form method="post" action="{{ route('admin.users.addresses.destroy', [$user, $address]) }}">
                                    @csrf
                                    @method('delete')
                                    <button class="btn btn-sm btn-outline-danger">Remove</button>
                                </form>
                            </div>
                        </div>
                        <form method="post" action="{{ route('admin.users.addresses.update', [$user, $address]) }}" class="mt-3">
                            @csrf
                            @method('put')
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <input type="text" name="label" class="form-control" value="{{ $address->label }}" placeholder="Label">
                                </div>
                                <div class="col-md-6">
                                    <input type="text" name="line1" class="form-control" value="{{ $address->line1 }}" placeholder="Line 1" required>
                                </div>
                                <div class="col-md-6">
                                    <input type="text" name="line2" class="form-control" value="{{ $address->line2 }}" placeholder="Line 2">
                                </div>
                                <div class="col-md-6">
                                    <input type="text" name="city" class="form-control" value="{{ $address->city }}" placeholder="City" required>
                                </div>
                                <div class="col-md-6">
                                    <input type="text" name="state" class="form-control" value="{{ $address->state }}" placeholder="State">
                                </div>
                                <div class="col-md-6">
                                    <input type="text" name="postal_code" class="form-control" value="{{ $address->postal_code }}" placeholder="Postal code">
                                </div>
                                <div class="col-md-6">
                                    <input type="text" name="country" class="form-control" value="{{ $address->country }}" placeholder="Country">
                                </div>
                                <div class="col-md-6 d-flex align-items-center">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_default" value="1" {{ $address->is_default ? 'checked' : '' }}>
                                        <label class="form-check-label">Default</label>
                                    </div>
                                </div>
                            </div>
                            <button class="btn btn-sm btn-outline-secondary mt-2">Update Address</button>
                        </form>
                    </div>
                @empty
                    <div class="text-muted">No saved addresses yet.</div>
                @endforelse
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h5 class="card-title mb-3">Add Address</h5>
                <form method="post" action="{{ route('admin.users.addresses.store', $user) }}">
                    @csrf
                    <div class="row g-2">
                        <div class="col-md-6">
                            <input type="text" name="label" class="form-control" placeholder="Label (Home, Work)">
                        </div>
                        <div class="col-md-6">
                            <input type="text" name="line1" class="form-control" placeholder="Line 1" required>
                        </div>
                        <div class="col-md-6">
                            <input type="text" name="line2" class="form-control" placeholder="Line 2">
                        </div>
                        <div class="col-md-6">
                            <input type="text" name="city" class="form-control" placeholder="City" required>
                        </div>
                        <div class="col-md-6">
                            <input type="text" name="state" class="form-control" placeholder="State">
                        </div>
                        <div class="col-md-6">
                            <input type="text" name="postal_code" class="form-control" placeholder="Postal code">
                        </div>
                        <div class="col-md-6">
                            <input type="text" name="country" class="form-control" placeholder="Country" value="Nigeria">
                        </div>
                        <div class="col-md-6 d-flex align-items-center">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_default" value="1">
                                <label class="form-check-label">Make default</label>
                            </div>
                        </div>
                    </div>
                    <button class="btn btn-sm btn-primary mt-2">Add Address</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
