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

    {{-- Filter Tabs --}}
    <ul class="nav nav-pills mb-4">
        <li class="nav-item">
            <a class="nav-link {{ !$filter ? 'active' : '' }}" href="{{ route('shop.custom-frock.index') }}">All</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $filter === 'active' ? 'active' : '' }}" href="{{ route('shop.custom-frock.index', ['filter' => 'active']) }}">Active</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $filter === 'completed' ? 'active' : '' }}" href="{{ route('shop.custom-frock.index', ['filter' => 'completed']) }}">Completed</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $filter === 'cancelled' ? 'active' : '' }}" href="{{ route('shop.custom-frock.index', ['filter' => 'cancelled']) }}">Cancelled</a>
        </li>
    </ul>

    @if ($orders->isEmpty())
        <div class="text-center py-5">
            <i class="bi bi-stars display-1 text-muted"></i>
            <h4 class="mt-3">No custom orders yet</h4>
            <p class="text-muted">Create something special with KidsFlairr and bring your idea to life.</p>
            <a href="{{ route('shop.custom-frock.create') }}" class="btn btn-primary mt-2">Create a Custom Order</a>
        </div>
    @else
        <div class="row g-3">
            @foreach ($orders as $order)
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 shadow-sm">
                        {{-- Thumbnail --}}
                        <div class="position-relative" style="height:180px; background:#f8f9fa; overflow:hidden; border-radius:.375rem .375rem 0 0;">
                            @php $thumb = $order->files->first(); @endphp
                            @if ($thumb && str_starts_with($thumb->mime_type, 'image/'))
                                <img src="{{ route('shop.custom-frock.file', [$order, $thumb]) }}"
                                     alt="{{ $order->custom_order_number }}"
                                     style="width:100%;height:100%;object-fit:cover;" loading="lazy" decoding="async">
                            @else
                                <div class="d-flex align-items-center justify-content-center h-100">
                                    <i class="bi bi-scissors display-3 text-muted opacity-50"></i>
                                </div>
                            @endif
                            {{-- Status Badge (overlay) --}}
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
                            } }} position-absolute top-0 end-0 m-2">
                                {{ $order->status_label }}
                            </span>
                        </div>
                        <div class="card-body">
                            <h6 class="card-title mb-1">{{ $order->getCustomizationValue('dress_style') ?: 'Custom Frock' }}</h6>
                            <p class="text-muted small mb-2">{{ $order->custom_order_number }}</p>
                            @if ($order->child_name)
                                <p class="text-muted small mb-2">
                                    {{ $order->child_name }}@if ($order->child_age) ({{ $order->child_age }} years)@endif
                                </p>
                            @endif
                            <p class="text-muted small mb-2">
                                <i class="bi bi-calendar3 me-1"></i>
                                {{ $order->submitted_at ? $order->submitted_at->format('d M Y') : 'Draft' }}
                            </p>
                            @if ($order->total_amount > 0)
                                <p class="fw-bold mb-2">₦{{ number_format($order->total_amount, 2) }}</p>
                                @if ($order->payment_status === 'paid')
                                    <span class="badge text-bg-success"><i class="bi bi-check-circle me-1"></i>Paid</span>
                                @elseif ($order->amount_paid > 0)
                                    <span class="badge text-bg-warning text-dark">Partially Paid</span>
                                @endif
                            @endif
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
