@extends('layouts.admin')

@section('title', 'Custom Orders')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Custom Orders</h1>
</div>

{{-- Stats --}}
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h4 class="mb-0">{{ $stats['total'] }}</h4>
                <small class="text-muted">Total Requests</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center border-warning">
            <div class="card-body">
                <h4 class="mb-0 text-warning">{{ $stats['pending'] }}</h4>
                <small class="text-muted">Pending Review</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center border-info">
            <div class="card-body">
                <h4 class="mb-0 text-info">{{ $stats['quoted'] }}</h4>
                <small class="text-muted">Awaiting Approval</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center border-success">
            <div class="card-body">
                <h4 class="mb-0 text-success">{{ $stats['in_production'] }}</h4>
                <small class="text-muted">In Production</small>
            </div>
        </div>
    </div>
</div>

{{-- Filters --}}
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="Search by number, name..." value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    @foreach (\App\Models\CustomOrder::STATUS_LABELS as $value => $label)
                        <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

{{-- Orders Table --}}
<div class="card shadow-sm">
    <div class="card-body">
        @if ($orders->isEmpty())
            <p class="text-center text-muted py-4">No custom orders found.</p>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Child</th>
                            <th>Design</th>
                            <th>Status</th>
                            <th>Quote</th>
                            <th>Submitted</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($orders as $order)
                            <tr>
                                <td class="fw-bold">{{ $order->custom_order_number }}</td>
                                <td>{{ $order->user?->name }}</td>
                                <td>{{ $order->child_name }} {{ $order->child_age ? "({$order->child_age}y)" : '' }}</td>
                                <td>{{ $order->getCustomizationValue('dress_style') ?: 'Custom' }}</td>
                                <td>
                                    <span class="badge bg-{{ match($order->status) {
                                        'draft' => 'secondary',
                                        'submitted', 'under_review', 'needs_information' => 'info',
                                        'quote_pending', 'quoted', 'needs_revision' => 'warning',
                                        'customer_approved', 'payment_pending' => 'primary',
                                        'paid', 'production_pending', 'in_production' => 'success',
                                        'quality_check', 'rework_required' => 'info',
                                        'ready_for_delivery', 'shipped', 'ready_for_pickup' => 'success',
                                        'completed' => 'dark',
                                        'cancelled', 'rejected' => 'danger',
                                        'quote_expired' => 'warning',
                                        default => 'secondary',
                                    } }}">
                                        {{ $order->status_label }}
                                    </span>
                                </td>
                                <td>
                                    @if ($order->latestQuote())
                                        ₦{{ number_format($order->latestQuote()->total, 2) }}
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>{{ $order->submitted_at?->format('d M Y g:i A') ?: '-' }}</td>
                                <td>
                                    <a href="{{ route('admin.custom-orders.show', $order) }}" class="btn btn-sm btn-outline-primary">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $orders->withQueryString()->links() }}
        @endif
    </div>
</div>
@endsection
