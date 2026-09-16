@extends('layouts.delivery-portal', ['title' => 'My Deliveries'])
@section('content')
<h5 class="mb-3">My Deliveries</h5>

<div class="d-flex gap-2 flex-wrap mb-3">
    @php($filters = ['assigned' => 'Assigned', 'received' => 'Received', 'out_for_delivery' => 'Out for Delivery', 'delivered' => 'Delivered', 'failed' => 'Issues'])
    @foreach($filters as $key => $label)
        <a href="{{ route('delivery-portal.deliveries', ['filter' => $key]) }}"
           class="btn btn-sm {{ $filter === $key ? 'btn-primary' : 'btn-outline-primary' }}">
            {{ $label }}
        </a>
    @endforeach
</div>

@if($deliveries->isEmpty())
    <div class="card">
        <div class="card-body text-center text-muted py-5">
            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
            No {{ $filter }} deliveries.
        </div>
    </div>
@else
    @foreach($deliveries as $order)
        <div class="card mb-3 delivery-card {{ $order->delivery_status }}">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h6 class="mb-0 fw-bold">{{ $order->reference }}</h6>
                        <small class="text-muted">{{ $order->customer?->name ?? $order->guest_name ?? '-' }}</small>
                    </div>
                    @if($order->delivery_status === 'assigned')
                        <span class="badge bg-primary">{{ $order->getDeliveryStatusLabel() }}</span>
                    @elseif($order->delivery_status === 'received')
                        <span class="badge bg-warning text-dark">{{ $order->getDeliveryStatusLabel() }}</span>
                    @elseif($order->delivery_status === 'out_for_delivery')
                        <span class="badge bg-info">{{ $order->getDeliveryStatusLabel() }}</span>
                    @elseif($order->delivery_status === 'delivered')
                        <span class="badge bg-success">{{ $order->getDeliveryStatusLabel() }}</span>
                    @elseif($order->delivery_status === 'failed')
                        <span class="badge bg-danger">{{ $order->getDeliveryStatusLabel() }}</span>
                    @else
                        <span class="badge bg-secondary">{{ $order->getDeliveryStatusLabel() }}</span>
                    @endif
                </div>
                <div class="mb-2">
                    <small class="text-muted d-block"><i class="bi bi-geo-alt me-1"></i>{{ $order->deliveryLocation?->name ?? '-' }}</small>
                    @if($order->delivery_address)
                        <small class="text-muted d-block">{{ Str::limit($order->delivery_address, 60) }}</small>
                    @endif
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted">Payment:</small>
                        @if($order->payment_status === 'paid')
                            <span class="badge bg-success">PAID</span>
                        @elseif($order->payment_status === 'partial')
                            <span class="badge bg-warning text-dark">PARTIAL</span>
                        @elseif($order->payment_status === 'unpaid')
                            <span class="badge bg-danger">UNPAID</span>
                        @else
                            <span class="badge bg-secondary">{{ strtoupper($order->payment_status ?? 'unpaid') }}</span>
                        @endif
                        @if($order->delivery_charge_amount)
                            <small class="text-muted ms-2">Fee: &#8358;{{ number_format($order->delivery_charge_amount, 2) }}</small>
                        @endif
                    </div>
                    <a href="{{ route('delivery-portal.deliveries.show', $order) }}" class="btn btn-sm btn-outline-primary">
                        View <i class="bi bi-chevron-right"></i>
                    </a>
                </div>
            </div>
        </div>
    @endforeach
@endif
@endsection
