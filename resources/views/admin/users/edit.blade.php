@extends('layouts.admin', ['title' => 'Edit User'])

@section('content')
@php
    $roles = \App\Models\User::roleOptions();
    $staffTypes = \App\Models\User::staffTypes();
@endphp
<div class="mb-3">
    <h3 class="mb-0"><i class="bi bi-person-badge"></i> Edit User</h3>
    <p class="text-muted">Update the user's details and role.</p>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <form method="post" action="{{ route('admin.users.update', $user) }}">
            @csrf
            @method('put')

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Roles</label>
                    <select name="roles[]" class="form-select" multiple required size="5">
                        @php $selectedRoles = old('roles', $user->roles->pluck('name')->all() ?: [$user->role]); @endphp
                        @foreach($roles as $value => $label)
                            <option value="{{ $value }}" {{ in_array($value, $selectedRoles, true) ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">Hold Ctrl/Cmd or use multiple selections to assign more than one role.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Staff Type (optional)</label>
                    <select name="staff_type" class="form-select">
                        <option value="">No staff type</option>
                        @foreach($staffTypes as $value => $label)
                            <option value="{{ $value }}" {{ old('staff_type', $user->staff_type) === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">Only used when role is Staff.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" minlength="8">
                    <div class="form-text">Leave blank to keep the current password.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <select name="is_active" class="form-select">
                        <option value="1" {{ old('is_active', $user->is_active ? '1' : '0') === '1' ? 'selected' : '' }}>Active</option>
                        <option value="0" {{ old('is_active', $user->is_active ? '1' : '0') === '0' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
            </div>

            <div class="mt-4">
                <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Cancel</a>
                <button class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endsection
