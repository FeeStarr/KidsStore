@extends('layouts.pickup-portal')

@section('title', 'Reports')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-flag"></i> Reports</h4>
    <a href="{{ route('pickup-portal.reports.create') }}" class="btn btn-sm btn-primary">
        <i class="bi bi-plus-lg"></i> New Report
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Order</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reports as $report)
                    <tr>
                        <td>{{ $report->id }}</td>
                        <td><span class="badge bg-{{ match($report->type) {
                            'missing_order' => 'danger',
                            'missing_item' => 'warning',
                            'damaged_item' => 'info',
                            'wrong_item' => 'primary',
                            'customer_no_show' => 'secondary',
                            default => 'dark',
                        } }}">{{ $report->type_label }}</span></td>
                        <td>{{ Str::limit($report->description, 60) }}</td>
                        <td>{{ $report->order ? '#' . $report->order->id : '-' }}</td>
                        <td><span class="badge bg-{{ match($report->status) {
                            'open' => 'danger',
                            'investigating' => 'warning',
                            'resolved' => 'success',
                            'dismissed' => 'secondary',
                            default => 'dark',
                        } }}">{{ $report->status_label }}</span></td>
                        <td>{{ $report->created_at->format('M d, Y') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No reports filed yet</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{ $reports->links() }}
@endsection
