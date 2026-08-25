@extends('layouts.admin')

@section('title', 'Report #' . $report->id)

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.pickup-reports.index') }}" class="text-decoration-none">← Back to Reports</a>
    <h4 class="mt-2 mb-0"><i class="bi bi-flag"></i> Report #{{ $report->id }}</h4>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="row g-4">
    <div class="col-md-8">
        <div class="card shadow-sm mb-4">
            <div class="card-header fw-bold">Report Details</div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <tr>
                        <td class="text-muted" style="width:140px;">Type</td>
                        <td><span class="badge bg-{{ match($report->type) {
                            'missing_order' => 'danger',
                            'missing_item' => 'warning',
                            'damaged_item' => 'info',
                            'wrong_item' => 'primary',
                            'customer_no_show' => 'secondary',
                            default => 'dark',
                        } }} fs-6">{{ $report->type_label }}</span></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Station</td>
                        <td>{{ $report->station->name ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Order</td>
                        <td>
                            @if($report->order)
                                <a href="{{ route('admin.orders.show', $report->order) }}">#{{ $report->order->id }}</a>
                                - {{ $report->order->customer->name ?? 'Guest' }}
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                    @if($report->orderItem)
                    <tr>
                        <td class="text-muted">Item</td>
                        <td>{{ $report->orderItem->product->name ?? '-' }} ({{ $report->orderItem->variant->name ?? '-' }})</td>
                    </tr>
                    @endif
                    <tr>
                        <td class="text-muted">Description</td>
                        <td>{{ $report->description }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Filed</td>
                        <td>{{ $report->created_at->format('M d, Y g:i A') }}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header fw-bold">Update Status</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.pickup-reports.update', $report) }}">
                    @csrf
                    @method('PUT')
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select name="status" id="status" class="form-select" required>
                            @foreach(\App\Models\PickupReport::STATUSES as $val => $label)
                                <option value="{{ $val }}" {{ $report->status === $val ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="admin_notes" class="form-label">Admin Notes</label>
                        <textarea name="admin_notes" id="admin_notes" class="form-control" rows="4" placeholder="Internal notes about this report...">{{ $report->admin_notes }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100" style="border-radius:50px;">
                        <i class="bi bi-check-lg me-1"></i> Update Report
                    </button>
                </form>
            </div>
        </div>

        @if($report->order)
        <div class="card shadow-sm">
            <div class="card-header fw-bold">Order Info</div>
            <div class="card-body">
                <p class="mb-1"><strong>Status:</strong> {{ $report->order->status }}</p>
                <p class="mb-1"><strong>Payment:</strong> {{ $report->order->payment_status }}</p>
                <p class="mb-1"><strong>Customer:</strong> {{ $report->order->customer->name ?? '-' }}</p>
                <p class="mb-0"><strong>Total:</strong> ₦{{ number_format($report->order->total, 2) }}</p>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
