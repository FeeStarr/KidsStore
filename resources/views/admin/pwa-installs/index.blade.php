@extends('layouts.admin')

@section('title', 'PWA Installs')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-phone"></i> PWA Installs</h4>
    <a href="{{ route('admin.dashboard') }}" class="btn btn-sm btn-outline-secondary">← Dashboard</a>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-2">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body py-3">
                <h3 class="text-primary mb-0">{{ $stats['total'] }}</h3>
                <small class="text-muted">Total</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body py-3">
                <h3 class="text-success mb-0">{{ $stats['today'] }}</h3>
                <small class="text-muted">Today</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body py-3">
                <h3 class="text-info mb-0">{{ $stats['thisWeek'] }}</h3>
                <small class="text-muted">This Week</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body py-3">
                <h3 class="mb-0">{{ $stats['ios'] }}</h3>
                <small class="text-muted">iOS</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-2">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body py-3">
                <h3 class="mb-0">{{ $stats['android'] }}</h3>
                <small class="text-muted">Android</small>
            </div>
        </div>
    </div>
</div>

<!-- Table -->
<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Platform</th>
                        <th>Browser</th>
                        <th>User</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($installs as $install)
                    <tr>
                        <td>{{ $install->id }}</td>
                        <td>{{ $install->created_at }}</td>
                        <td>
                            @if ($install->platform === 'ios')
                                <span class="badge bg-dark">iOS</span>
                            @elseif ($install->platform === 'android')
                                <span class="badge bg-success">Android</span>
                            @else
                                <span class="badge bg-secondary">{{ $install->platform ?? '-' }}</span>
                            @endif
                        </td>
                        <td>{{ $install->browser ?? '-' }}</td>
                        <td>{{ $install->user_id ?? 'Guest' }}</td>
                        <td><code>{{ $install->ip_address }}</code></td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No installs recorded yet</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{ $installs->links() }}
@endsection
