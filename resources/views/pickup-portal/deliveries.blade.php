@extends('layouts.pickup-portal', ['title' => 'Deliveries - '.session('portal_station_name')])
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Deliveries - {{ session('portal_station_name') }}</h4>
    <a href="{{ route('pickup-portal.dashboard') }}" class="btn btn-sm btn-outline-secondary">Back</a>
</div>

@foreach($orders as $order)
    <div class="card mb-2">
        <div class="card-body d-flex justify-content-between">
            <div>
                <div class="fw-semibold">{{ $order->reference }} - {{ $order->customer?->name }}</div>
                <div class="small text-muted">Placed: {{ $order->order_date->format('M d, Y g:i A') }} - Status: {{ $order->getStatusLabel() }}</div>
            </div>
            <div class="text-end">
                <div class="fw-bold">₦{{ number_format($order->grand_total,2) }}</div>
                @if($order->payment_status !== 'paid')
                    <form method="post" action="{{ route('pickup-portal.record-payment', $order) }}" class="d-flex gap-2 mt-2">
                        @csrf
                        <input name="amount" type="number" step="0.01" min="0" placeholder="Amount" class="form-control form-control-sm" style="width:140px">
                        <select name="method" class="form-select form-select-sm" style="width:120px">
                            <option value="cash">Cash</option>
                            <option value="transfer">Transfer</option>
                        </select>
                        <button class="btn btn-sm btn-success">Record</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
@endforeach

@endsection
