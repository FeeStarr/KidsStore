@extends('layouts.admin', ['title' => 'Dashboard'])

@section('content')
<h3 class="mb-4">Dashboard</h3>
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card stat-card text-bg-primary"><div class="card-body"><div class="text-uppercase small">Products</div><div class="display-6">{{ $stats['products'] }}</div></div></div></div>
    <div class="col-md-3"><div class="card stat-card text-bg-warning"><div class="card-body"><div class="text-uppercase small">Low Stock</div><div class="display-6">{{ $stats['low_stock'] }}</div></div></div></div>
    <div class="col-md-3"><div class="card stat-card text-bg-info"><div class="card-body"><div class="text-uppercase small">Pending Orders</div><div class="display-6">{{ $stats['pending_orders'] }}</div></div></div></div>
    <div class="col-md-3"><div class="card stat-card text-bg-secondary"><div class="card-body"><div class="text-uppercase small">Pending Purchases</div><div class="display-6">{{ $stats['pending_purchases'] }}</div></div></div></div>
    <div class="col-md-6"><div class="card stat-card text-bg-success"><div class="card-body"><div class="text-uppercase small">Total Revenue (paid)</div><div class="display-6">₦{{ number_format($stats['revenue_total'], 2) }}</div></div></div></div>
    <div class="col-md-6"><div class="card stat-card text-bg-dark"><div class="card-body"><div class="text-uppercase small">Total Orders Value</div><div class="display-6">₦{{ number_format($stats['orders_total'], 2) }}</div></div></div></div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card"><div class="card-header">Recent Orders</div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead><tr><th>Ref</th><th>Customer</th><th>Status</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                    @forelse($recentOrders as $o)
                        <tr>
                            <td><a href="{{ route('admin.orders.show', $o) }}">{{ $o->reference }}</a></td>
                            <td>{{ $o->customer?->name ?? 'â€”' }}</td>
                            <td><span class="badge text-bg-light">{{ $o->status }}</span></td>
                            <td class="text-end">₦{{ number_format($o->grand_total, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted p-3">No orders yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card"><div class="card-header">Low Stock</div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead><tr><th>Product</th><th>Qty</th><th>Reorder</th></tr></thead>
                    <tbody>
                    @forelse($lowStock as $i)
                        <tr>
                            <td>{{ $i->product->name }}</td>
                            <td>{{ $i->quantity }}</td>
                            <td>{{ $i->reorder_level }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted p-3">All products are well-stocked.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
