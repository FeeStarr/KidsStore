@extends('layouts.admin', ['title' => 'Vendor Approvals'])

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h3 class="mb-0"><i class="bi bi-person-check"></i> Vendor Approvals</h3>
        <p class="text-muted mb-0">Review and approve vendor applications.</p>
    </div>
</div>

<ul class="nav nav-pills mb-3">
    @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $value => $label)
        <li class="nav-item">
            <a class="nav-link {{ $status === $value ? 'active' : '' }}" href="{{ route('admin.vendor-approvals.index', ['status' => $value]) }}">
                {{ $label }}
            </a>
        </li>
    @endforeach
</ul>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>User</th>
                        <th>Requested</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($approvals as $approval)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $approval->user?->name ?? 'Unknown' }}</div>
                            <div class="text-muted small">{{ $approval->user?->email ?? '-' }}</div>
                        </td>
                        <td>{{ $approval->created_at->format('M d, Y g:i A') }}</td>
                        <td>
                            @php
                                $badge = match($approval->status) {
                                    'approved' => 'badge bg-success',
                                    'rejected' => 'badge bg-danger',
                                    default => 'badge bg-warning text-dark',
                                };
                            @endphp
                            <span class="{{ $badge }} text-capitalize">{{ $approval->status }}</span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.vendor-approvals.show', $approval) }}" class="btn btn-sm btn-outline-primary">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">No approvals found.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">
    {{ $approvals->links() }}
</div>
@endsection
