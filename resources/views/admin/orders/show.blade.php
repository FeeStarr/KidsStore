@extends('layouts.admin', ['title' => 'Order '.$order->reference])
@section('content')
<div class="d-flex justify-content-between mb-3">
    <h3>Order {{ $order->reference }}</h3>
    <div>
        @if($order->status === 'order placed')
            <form action="{{ route('admin.orders.confirm', $order) }}" method="post" class="d-inline">
                @csrf <button class="btn btn-success">Confirm</button>
            </form>
        @endif
        @if(in_array($order->status, ['confirmed']))
            <form action="{{ route('admin.orders.processing', $order) }}" method="post" class="d-inline">
                @csrf <button class="btn btn-secondary">Processing</button>
            </form>
        @endif
        @if(in_array($order->status, ['confirmed', 'processing']))
            <form action="{{ route('admin.orders.ship', $order) }}" method="post" class="d-inline">
                @csrf <button class="btn btn-info">Ship</button>
            </form>
            <form action="{{ route('admin.orders.ready-for-pickup', $order) }}" method="post" class="d-inline">
                @csrf <button class="btn btn-warning">Ready for Pick Up</button>
            </form>
        @endif
        @if(in_array($order->status, ['shipped', 'ready for pick up']))
            <form action="{{ route('admin.orders.deliver', $order) }}" method="post" class="d-inline">
                @csrf <button class="btn btn-primary">Deliver</button>
            </form>
        @endif
        @if($order->status !== 'cancelled')
            <form action="{{ route('admin.orders.cancel', $order) }}" method="post" class="d-inline"
                  data-confirm="This will cancel the order and restore inventory." data-confirm-title="Cancel Order?"
                  data-confirm-yes="Yes, cancel">
                @csrf <button class="btn btn-outline-danger">Cancel</button>
            </form>
        @endif
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6"><div class="card"><div class="card-body">
        <dl class="row mb-0">
            <dt class="col-4">Customer</dt><dd class="col-8">{{ $order->customer?->name ?? 'â€”' }}</dd>
            <dt class="col-4">Date</dt><dd class="col-8">{{ $order->order_date->format('Y-m-d') }}</dd>
            <dt class="col-4">Status</dt><dd class="col-8"><span class="badge text-bg-light">{{ $order->status }}</span></dd>
            <dt class="col-4">Payment</dt><dd class="col-8"><span class="badge text-bg-light">{{ $order->payment_status }}</span></dd>
        </dl>
    </div></div></div>
    <div class="col-md-6"><div class="card"><div class="card-body">
        <dl class="row mb-0">
            <dt class="col-6">Subtotal</dt><dd class="col-6 text-end">₦{{ number_format($order->subtotal, 2) }}</dd>
            <dt class="col-6">Discount</dt><dd class="col-6 text-end">{{ number_format($order->discount, 2) }}%</dd>
            <dt class="col-6">Shipping</dt><dd class="col-6 text-end">₦{{ number_format($order->shipping_fee, 2) }}</dd>
            <dt class="col-6 fw-bold">Grand Total</dt><dd class="col-6 text-end fw-bold">₦{{ number_format($order->grand_total, 2) }}</dd>
            <dt class="col-6">Paid</dt><dd class="col-6 text-end">₦{{ number_format($order->amount_paid, 2) }}</dd>
            <dt class="col-6 text-danger">Balance</dt><dd class="col-6 text-end text-danger">₦{{ number_format($order->balance, 2) }}</dd>
        </dl>
    </div></div></div>
</div>

<div class="card mb-3"><div class="card-header">Items</div>
<table class="table mb-0">
    <thead><tr><th>Product</th><th>Qty</th><th>Unit Price</th><th>Discount %</th><th class="text-end">Line Total</th></tr></thead>
    <tbody>
    @foreach($order->items as $it)
        <tr>
            <td>
                {{ $it->product->name }}
                @if($it->variant && $it->variant->options_label)
                    <small class="text-muted d-block">{{ $it->variant->options_label }}</small>
                @endif
            </td>
            <td>{{ $it->quantity }}</td>
            <td>₦{{ number_format($it->unit_price, 2) }}</td>
            <td>{{ number_format($it->discount, 2) }}%</td>
            <td class="text-end">₦{{ number_format($it->line_total, 2) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>

<div class="card mb-3"><div class="card-header d-flex justify-content-between">
    <span>Payments</span>
    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="collapse" data-bs-target="#pay-form">Record Payment</button>
</div>
<div class="collapse" id="pay-form">
    <div class="card-body bg-light">
        <form method="post" action="{{ route('admin.orders.payments.store', $order) }}">
            @csrf
            <div class="row g-2">
                <div class="col-md-3"><input type="date" name="payment_date" value="{{ now()->toDateString() }}" class="form-control" required></div>
                <div class="col-md-2"><input type="number" step="0.01" name="amount" placeholder="Amount" class="form-control" required></div>
                <div class="col-md-2">
                    <select name="method" class="form-select">
                        @foreach(['cash','card','transfer','mobile','other'] as $m)
                            <option value="{{ $m }}">{{ ucfirst($m) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3"><input name="transaction_id" placeholder="Transaction ID" class="form-control"></div>
                <div class="col-md-2"><button class="btn btn-primary w-100">Save</button></div>
            </div>
        </form>
    </div>
</div>
<table class="table mb-0">
    <thead><tr><th>Reference</th><th>Date</th><th>Method</th><th>Transaction</th><th class="text-end">Amount</th></tr></thead>
    <tbody>
    @forelse($order->payments as $p)
        <tr>
            <td>{{ $p->reference }}</td>
            <td>{{ $p->payment_date->format('Y-m-d') }}</td>
            <td>{{ $p->method }}</td>
            <td>{{ $p->transaction_id ?? 'â€”' }}</td>
            <td class="text-end">₦{{ number_format($p->amount, 2) }}</td>
        </tr>
    @empty
        <tr><td colspan="5" class="text-center text-muted p-3">No payments recorded.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
@endsection
