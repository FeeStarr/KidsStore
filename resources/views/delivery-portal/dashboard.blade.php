@extends('layouts.delivery-portal', ['title' => 'Dashboard'])
@section('content')
<h5 class="mb-1">Welcome, {{ $agent->name }}</h5>
<p class="text-muted mb-4">Account: {{ $agent->account_number }}</p>

<div class="row g-2">
    <div class="col-6 col-md-4 col-lg-2">
        <a href="{{ route('delivery-portal.deliveries', ['filter' => 'assigned']) }}" class="text-decoration-none">
            <div class="card stat-card assigned h-100">
                <div class="card-body text-center py-3">
                    <div class="fs-3 fw-bold text-primary">{{ $stats['assigned'] }}</div>
                    <div class="text-muted small">Assigned</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <a href="{{ route('delivery-portal.deliveries', ['filter' => 'received']) }}" class="text-decoration-none">
            <div class="card stat-card received h-100">
                <div class="card-body text-center py-3">
                    <div class="fs-3 fw-bold text-warning">{{ $stats['received'] }}</div>
                    <div class="text-muted small">Received</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <a href="{{ route('delivery-portal.deliveries', ['filter' => 'out_for_delivery']) }}" class="text-decoration-none">
            <div class="card h-100" style="border-left: 4px solid #0dcaf0;">
                <div class="card-body text-center py-3">
                    <div class="fs-3 fw-bold text-info">{{ $stats['out_for_delivery'] }}</div>
                    <div class="text-muted small">Out for Delivery</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <a href="{{ route('delivery-portal.deliveries', ['filter' => 'delivered']) }}" class="text-decoration-none">
            <div class="card stat-card delivered h-100">
                <div class="card-body text-center py-3">
                    <div class="fs-3 fw-bold text-success">{{ $stats['delivered'] }}</div>
                    <div class="text-muted small">Delivered</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <a href="{{ route('delivery-portal.deliveries', ['filter' => 'failed']) }}" class="text-decoration-none">
            <div class="card stat-card issues h-100">
                <div class="card-body text-center py-3">
                    <div class="fs-3 fw-bold text-danger">{{ $stats['failed'] }}</div>
                    <div class="text-muted small">Issues</div>
                </div>
            </div>
        </a>
    </div>
</div>
@endsection
