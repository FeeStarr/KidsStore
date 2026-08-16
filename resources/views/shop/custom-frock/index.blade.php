@extends('layouts.shop')

@section('title', 'My Custom Orders')

@section('content')
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">My Custom Orders</h1>
        <a href="{{ route('shop.custom-frock.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Start Custom Order
        </a>
    </div>

    @if ($orders->isEmpty())
        <div class="text-center py-5">
            <i class="bi bi-stars display-1 text-muted"></i>
            <h4 class="mt-3">No custom orders yet</h4>
            <p class="text-muted">Create a frock designed specially for your little one.</p>
            <a href="{{ route('shop.custom-frock.create') }}" class="btn btn-primary mt-2">Start Custom Order</a>
        </div>
    @else
        <div class="row g-3">
            @foreach ($orders as $order)
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="fw-bold text-primary">{{ $order->custom_order_number }}</span>
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
                            </div>
                            <p class="text-muted small mb-2">
                                @if ($order->child_name)
                                    {{ $order->child_name }}
                                    @if ($order->child_age) ({{ $order->child_age }} years) @endif
                                @endif
                            </p>
                            <p class="mb-2">
                                {{ $order->getCustomizationValue('dress_style') ?: 'Custom Frock' }}
                            </p>
                            @if ($order->total_amount > 0)
                                <p class="fw-bold mb-2">₦{{ number_format($order->total_amount, 2) }}</p>
                            @endif
                            <small class="text-muted">Submitted {{ $order->submitted_at?->diffForHumans() ?: 'as draft' }}</small>
                        </div>
                        <div class="card-footer bg-transparent">
                            <a href="{{ route('shop.custom-frock.show', $order) }}" class="btn btn-outline-primary btn-sm w-100">View Details</a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-4">
            {{ $orders->links() }}
        </div>
    @endif
</div>
@endsection
