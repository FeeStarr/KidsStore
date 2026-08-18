@extends('layouts.admin', ['title' => 'Purchase '.$purchase->display_number])
@section('content')
<div class="d-flex justify-content-between mb-3">
    <h3>Purchase {{ $purchase->display_number }}</h3>
    <div>
        @if($purchase->status === 'pending')
            <a href="{{ route('admin.purchases.edit', $purchase) }}" class="btn btn-outline-secondary">
                <i class="bi bi-pencil"></i> Edit
            </a>
            <form action="{{ route('admin.purchases.receive', $purchase) }}" method="post" class="d-inline">
                @csrf <button class="btn btn-success"><i class="bi bi-check2"></i> Mark Received</button>
            </form>
        @endif
        @if($purchase->status !== 'cancelled')
            <form action="{{ route('admin.purchases.cancel', $purchase) }}" method="post" class="d-inline"
                  data-confirm="This will cancel the purchase order." data-confirm-title="Cancel Purchase?"
                  data-confirm-yes="Yes, cancel">
                @csrf <button class="btn btn-outline-danger">Cancel</button>
            </form>
        @endif
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6"><div class="card"><div class="card-body">
        <dl class="row mb-0">
            <dt class="col-4">Purchase Number</dt><dd class="col-8">{{ $purchase->display_number }}</dd>
            <dt class="col-4">Supplier</dt><dd class="col-8">{{ $purchase->supplier?->name ?? 'â€”' }}</dd>
            <dt class="col-4">Date</dt><dd class="col-8">{{ $purchase->purchase_date->format('Y-m-d H:i') }}</dd>
            <dt class="col-4">Status</dt><dd class="col-8"><span class="badge text-bg-light">{{ $purchase->status }}</span></dd>
            <dt class="col-4">Note</dt><dd class="col-8">{{ $purchase->note ?? 'â€”' }}</dd>
        </dl>
    </div></div></div>
    <div class="col-md-6"><div class="card"><div class="card-body">
        <dl class="row mb-0">
            <dt class="col-6">Total Cost Price</dt><dd class="col-6 text-end">₦{{ number_format($purchase->total_cost_price, 2) }}</dd>
            <dt class="col-6">Total Shipping</dt><dd class="col-6 text-end">₦{{ number_format($purchase->total_shipping_fee, 2) }}</dd>
            <dt class="col-6">Total Packaging</dt><dd class="col-6 text-end">₦{{ number_format($purchase->total_packaging_cost, 2) }}</dd>
            <dt class="col-6">Total Other</dt><dd class="col-6 text-end">₦{{ number_format($purchase->total_other_costs, 2) }}</dd>
            <dt class="col-6">Total Discount</dt><dd class="col-6 text-end">-₦{{ number_format($purchase->total_discount, 2) }}</dd>
            <dt class="col-6 fw-bold">Total Cost</dt><dd class="col-6 text-end fw-bold">₦{{ number_format($purchase->total_cost, 2) }}</dd>
        </dl>
    </div></div></div>
</div>

<div class="card"><div class="card-header">Items</div>
<table class="table mb-0 align-middle">
    <thead><tr>
        <th style="width:40px">#</th>
        <th style="width:44px"></th>
        <th>Product</th><th>Qty</th>
        <th>Cost</th><th>Shipping</th><th>Packaging</th><th>Other</th>
        <th>Selling</th><th>Discount %</th><th class="text-end">Line Total</th>
    </tr></thead>
    <tbody>
    @foreach($purchase->items as $it)
        @php
            $thumb = $it->variant?->image?->url ?? $it->product->images->first()?->url ?? '';
        @endphp
        <tr>
            <td class="text-muted">{{ $loop->iteration }}</td>
            <td>
                @if($thumb)
                    <img src="{{ $thumb }}" style="width:36px;height:36px;object-fit:cover;border-radius:.375rem;border:1px solid #dee2e6;" alt="">
                @else
                    <span class="d-inline-flex align-items-center justify-content-center bg-light text-muted" style="width:36px;height:36px;border-radius:.375rem;font-size:.7rem;"><i class="bi bi-image"></i></span>
                @endif
            </td>
            <td>
                {{ $it->product->name }}
                @if($it->variant && $it->variant->options_label)
                    <small class="text-muted d-block">{{ $it->variant->options_label }}</small>
                @endif
            </td>
            <td>{{ $it->quantity }}</td>
            <td>₦{{ number_format($it->cost_price, 2) }}</td>
            <td>₦{{ number_format($it->shipping_fee, 2) }}</td>
            <td>₦{{ number_format($it->packaging_cost, 2) }}</td>
            <td>₦{{ number_format($it->other_costs, 2) }}</td>
            <td>₦{{ number_format($it->selling_price, 2) }}</td>
            <td>{{ number_format($it->discount, 2) }}%</td>
            <td class="text-end">₦{{ number_format($it->line_total, 2) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>
@endsection
