@extends('layouts.admin', ['title' => 'Users'])

@section('content')
@php
    $roles = \App\Models\User::roleOptions();
    $staffTypes = \App\Models\User::staffTypes();
@endphp
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h3 class="mb-0"><i class="bi bi-people"></i> Users</h3>
        <p class="text-muted mb-0">Manage administrator and support accounts for the store.</p>
    </div>
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle"></i> New User</a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>
                            @php
                                $assignedRoles = $user->roles->pluck('name')->all() ?: [$user->role];
                                $badge = 'badge bg-secondary';
                                if (in_array(\App\Models\User::ROLE_SUPERADMIN, $assignedRoles, true)) {
                                    $badge = 'badge bg-dark';
                                } elseif (in_array(\App\Models\User::ROLE_ADMIN, $assignedRoles, true)) {
                                    $badge = 'badge bg-primary';
                                } elseif (in_array(\App\Models\User::ROLE_VENDOR, $assignedRoles, true)) {
                                    $badge = 'badge bg-success';
                                } elseif (in_array(\App\Models\User::ROLE_STAFF, $assignedRoles, true)) {
                                    $badge = 'badge bg-info text-dark';
                                } elseif (in_array(\App\Models\User::ROLE_DELIVERY_AGENT, $assignedRoles, true)) {
                                    $badge = 'badge bg-warning text-dark';
                                }
                            @endphp
                            <span class="{{ $badge }}">{{ implode(', ', array_map(fn($role) => $roles[$role] ?? $role, $assignedRoles)) }}</span>
                            @if(in_array(\App\Models\User::ROLE_STAFF, $assignedRoles, true) && $user->staff_type)
                                <div class="text-muted small">{{ $staffTypes[$user->staff_type] ?? $user->staff_type }}</div>
                            @endif
                        </td>
                        <td>
                            @if($user->is_active)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-secondary">Inactive</span>
                            @endif
                        </td>
                        <td>{{ $user->created_at->format('M d, Y') }}</td>
                        <td class="text-end">
                            <a href="{{ route('admin.users.show', $user) }}" class="btn btn-sm btn-outline-primary">View</a>
                            <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No users found.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">
    {{ $users->links() }}
</div>
@endsection
