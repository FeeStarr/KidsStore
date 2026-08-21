@extends('layouts.admin')

@section('title', 'Pickup Reports')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-flag"></i> Pickup Reports</h4>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body py-3">
                <h3 class="text-danger mb-0">{{ $stats['open'] }}</h3>
                <small class="text-muted">Open</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body py-3">
                <h3 class="text-warning mb-0">{{ $stats['investigating'] }}</h3>
                <small class="text-muted">Investigating</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body py-3">
                <h3 class="text-success mb-0">{{ $stats['resolved'] }}</h3>
                <small class="text-muted">Resolved</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body py-3">
                <h3 class="text-primary mb-0">{{ $stats['total'] }}</h3>
                <small class="text-muted">Total</small>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Station</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Order</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reports as $report)
                    <tr>
                        <td>{{ $report->id }}</td>
                        <td>{{ $report->station->name ?? '—' }}</td>
                        <td><span class="badge bg-{{ match($report->type) {
                            'missing_order' => 'danger',
                            'missing_item' => 'warning',
                            'damaged_item' => 'info',
                            'wrong_item' => 'primary',
                            'customer_no_show' => 'secondary',
                            default => 'dark',
                        } }}">{{ $report->type_label }}</span></td>
                        <td>{{ Str::limit($report->description, 50) }}</td>
                        <td>{{ $report->order ? '#' . $report->order->id : '—' }}</td>
                        <td><span class="badge bg-{{ match($report->status) {
                            'open' => 'danger',
                            'investigating' => 'warning',
                            'resolved' => 'success',
                            'dismissed' => 'secondary',
                            default => 'dark',
                        } }}">{{ $report->status_label }}</span></td>
                        <td>{{ $report->created_at->format('M d, Y') }}</td>
                        <td><a href="{{ route('admin.pickup-reports.show', $report) }}" class="btn btn-sm btn-outline-primary">View</a></td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No reports yet</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{ $reports->links() }}
@endsection
