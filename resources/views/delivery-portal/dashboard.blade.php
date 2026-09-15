@extends('layouts.delivery-portal', ['title' => 'Dashboard'])
@section('content')
<h5 class="mb-1">Welcome, {{ $agent->name }}</h5>
<p class="text-muted mb-4">Account: {{ $agent->account_number }}</p>

<div class="row g-3">
    <div class="col-6 col-md-3">
        <a href="{{ route('delivery-portal.deliveries', ['filter' => 'assigned']) }}" class="text-decoration-none">
            <div class="card stat-card assigned h-100">
                <div class="card-body text-center">
                    <div class="fs-1 fw-bold text-primary">{{ $stats['assigned'] }}</div>
                    <div class="text-muted">Assigned</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="{{ route('delivery-portal.deliveries', ['filter' => 'received']) }}" class="text-decoration-none">
            <div class="card stat-card received h-100">
                <div class="card-body text-center">
                    <div class="fs-1 fw-bold text-warning">{{ $stats['received'] }}</div>
                    <div class="text-muted">Received</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="{{ route('delivery-portal.deliveries', ['filter' => 'delivered']) }}" class="text-decoration-none">
            <div class="card stat-card delivered h-100">
                <div class="card-body text-center">
                    <div class="fs-1 fw-bold text-success">{{ $stats['delivered'] }}</div>
                    <div class="text-muted">Delivered</div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="{{ route('delivery-portal.deliveries', ['filter' => 'failed']) }}" class="text-decoration-none">
            <div class="card stat-card issues h-100">
                <div class="card-body text-center">
                    <div class="fs-1 fw-bold text-danger">{{ $stats['failed'] }}</div>
                    <div class="text-muted">Issues</div>
                </div>
            </div>
        </a>
    </div>
</div>
@endsection
