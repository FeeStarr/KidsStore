@extends('layouts.admin', ['title' => 'Users'])

@section('content')
@php
    $roles      = \App\Models\User::roleOptions();
    $staffTypes = \App\Models\User::staffTypes();
@endphp

<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h3 class="mb-0"><i class="bi bi-people"></i> Users</h3>
        <p class="text-muted mb-0">Manage administrator and customer accounts for the store.</p>
    </div>
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle"></i> New User
    </a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="users-table" class="table table-hover mb-0 align-middle w-100">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>2FA</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th data-dt-no-export class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($users as $user)
                    @php
                        $assignedRoles = $user->roles->pluck('name')->all() ?: [$user->role];
                        $badge = 'badge bg-secondary';
                        if (in_array(\App\Models\User::ROLE_SUPERADMIN, $assignedRoles, true))       $badge = 'badge bg-dark';
                        elseif (in_array(\App\Models\User::ROLE_ADMIN, $assignedRoles, true))        $badge = 'badge bg-primary';
                        elseif (in_array(\App\Models\User::ROLE_VENDOR, $assignedRoles, true))       $badge = 'badge bg-success';
                        elseif (in_array(\App\Models\User::ROLE_STAFF, $assignedRoles, true))        $badge = 'badge bg-info text-dark';
                        elseif (in_array(\App\Models\User::ROLE_DELIVERY_AGENT, $assignedRoles, true)) $badge = 'badge bg-warning text-dark';
                    @endphp
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>
                            <span class="{{ $badge }}">
                                {{ implode(', ', array_map(fn($r) => $roles[$r] ?? $r, $assignedRoles)) }}
                            </span>
                            @if(in_array(\App\Models\User::ROLE_STAFF, $assignedRoles, true) && $user->staff_type)
                                <div class="text-muted small">{{ $staffTypes[$user->staff_type] ?? $user->staff_type }}</div>
                            @endif
                        </td>
                        <td>
                            @if($user->two_factor_enabled)
                                <span class="badge bg-primary"><i class="bi bi-shield-fill me-1"></i>On</span>
                            @else
                                <span class="badge bg-light text-muted border">Off</span>
                            @endif
                        </td>
                        <td>
                            @if($user->is_active)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-secondary">Inactive</span>
                            @endif
                        </td>
                        <td data-order="{{ $user->created_at->timestamp }}">
                            {{ $user->created_at->format('M d, Y') }}
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('admin.users.show', $user) }}" class="btn btn-sm btn-outline-primary">View</a>
                            <a href="{{ route('admin.users.edit', $user) }}"  class="btn btn-sm btn-outline-secondary">Edit</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
$(function () {
    $('#users-table').DataTable({
        order: [[5, 'desc']],
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
        columnDefs: [
            { targets: -1, orderable: false, searchable: false }
        ],
        layout: {
            topStart: {
                buttons: [
                    { extend: 'copy',  className: 'btn btn-sm btn-primary',   exportOptions: { columns: ':not([data-dt-no-export])' } },
                    { extend: 'csv',   className: 'btn btn-sm btn-success',   filename: 'users', exportOptions: { columns: ':not([data-dt-no-export])' } },
                    { extend: 'excel', className: 'btn btn-sm btn-success',   filename: 'users', exportOptions: { columns: ':not([data-dt-no-export])' } },
                    { extend: 'pdf',   className: 'btn btn-sm btn-danger',    filename: 'users', orientation: 'landscape', pageSize: 'A4', exportOptions: { columns: ':not([data-dt-no-export])' } },
                    { extend: 'print', className: 'btn btn-sm btn-secondary', exportOptions: { columns: ':not([data-dt-no-export])' } }
                ]
            },
            topEnd: ['pageLength', 'search']
        }
    });
});
</script>
@endpush

@endsection
