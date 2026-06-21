@extends('layouts.pickup-portal', ['title' => 'Payments — '.session('portal_station_name')])
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Payments — {{ session('portal_station_name') }}</h4>
    <a href="{{ route('pickup-portal.dashboard') }}" class="btn btn-sm btn-outline-secondary">Back</a>
</div>

<div class="card"><div class="card-body">
    <table class="table table-sm">
        <thead><tr><th>Date</th><th>Order</th><th>Customer</th><th class="text-end">Amount</th><th>Method</th><th></th></tr></thead>
        <tbody>
        @foreach($payments as $p)
            <tr>
                <td>{{ $p->payment_date->toDateString() }}</td>
                <td><a href="{{ route('pickup-portal.dashboard') }}">{{ $p->order->reference }}</a></td>
                <td>{{ $p->order->customer?->name }}</td>
                <td class="text-end">₦{{ number_format($p->amount,2) }}</td>
                <td>{{ $p->method }}</td>
                <td class="text-end small text-muted">{{ $p->note }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div></div>

@endsection
