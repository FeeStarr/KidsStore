@extends('layouts.shop', ['title' => 'Order '.$order->reference])
@section('content')

<div class="d-flex justify-content-between mb-3">
    <h3>Order {{ $order->reference }}</h3>
    <a href="{{ route('shop.account.orders.index') }}" class="btn btn-outline-secondary btn-sm">Back to orders</a>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <h6>Status</h6>
            <p class="mb-1"><span class="badge text-bg-light">{{ $order->getStatusLabel() }}</span> &middot;
                <span class="badge text-bg-light">{{ ucfirst($order->payment_status) }}</span></p>
            <small class="text-muted">Placed on {{ $order->order_date->format('M d, Y') }}</small>
        </div></div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <h6>Totals</h6>
            <dl class="row mb-0">
                <dt class="col-6">Subtotal</dt><dd class="col-6 text-end">&#8358;{{ number_format($order->subtotal, 2) }}</dd>
                <dt class="col-6">Discount</dt><dd class="col-6 text-end">{{ number_format($order->discount, 2) }}%</dd>
                <dt class="col-6">Shipping</dt><dd class="col-6 text-end">&#8358;{{ number_format($order->shipping_fee, 2) }}</dd>
                <dt class="col-6 fw-bold">Total</dt><dd class="col-6 text-end fw-bold">&#8358;{{ number_format($order->grand_total, 2) }}</dd>
                <dt class="col-6">Paid</dt><dd class="col-6 text-end">&#8358;{{ number_format($order->amount_paid, 2) }}</dd>
            </dl>
        </div></div>
    </div>
</div>

<div class="card border-0 shadow-sm">
<table class="table mb-0 align-middle">
    <thead><tr><th colspan="2">Item</th><th>Qty</th><th class="text-end">Unit</th><th class="text-end">Line Total</th></tr></thead>
    <tbody>
    @foreach($order->items as $it)
        <tr>
            <td style="width:80px">
                @if($it->product?->primaryImage)
                    <img src="{{ $it->product->primaryImage->url }}" style="width:56px;height:56px;object-fit:cover;border-radius:.35rem">
                @endif
            </td>
            <td>
                @if($it->product)
                    <a href="{{ route('shop.products.show', $it->product) }}">{{ $it->product->name }}</a>
                @else
                    {{ $it->product_id }}
                @endif
                @if($it->variant && $it->variant->options_label)
                    <div class="small text-muted">{{ $it->variant->options_label }}</div>
                @endif
            </td>
            <td>{{ $it->quantity }}</td>
            <td class="text-end">&#8358;{{ number_format($it->unit_price, 2) }}</td>
            <td class="text-end">&#8358;{{ number_format($it->line_total, 2) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>

@endsection
