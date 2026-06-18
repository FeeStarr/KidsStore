@extends('layouts.shop', ['title' => 'My Orders'])
@section('content')
<h3 class="mb-3">My Orders</h3>

<div class="card border-0 shadow-sm">
<table class="table mb-0 align-middle">
    <thead><tr><th>Reference</th><th>Date</th><th>Status</th><th>Payment</th><th class="text-end">Total</th><th></th></tr></thead>
    <tbody>
    @forelse($orders as $o)
        <tr>
            <td><a href="{{ route('shop.account.orders.show', $o) }}">{{ $o->reference }}</a></td>
            <td>{{ $o->order_date->format('M d, Y') }}</td>
            <td><span class="badge text-bg-light">{{ $o->getStatusLabel() }}</span></td>
            <td><span class="badge text-bg-light">{{ ucfirst($o->payment_status) }}</span></td>
            <td class="text-end">&#8358;{{ number_format($o->total_amount ?: $o->grand_total, 2) }}</td>
            <td class="text-end"><a href="{{ route('shop.account.orders.show', $o) }}" class="btn btn-sm btn-outline-secondary">View</a></td>
        </tr>
    @empty
        <tr><td colspan="6" class="text-center text-muted py-4">You haven't placed any orders yet.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
@endsection
