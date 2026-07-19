@extends('layouts.admin', ['title' => 'Pickup Payout Ledger'])
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Pickup Payout Ledger</h3>
    <form class="d-flex gap-2" method="get" action="">
        <select name="station_id" class="form-select form-select-sm">
            <option value="">All Stations</option>
            @foreach($stations as $s)
                <option value="{{ $s->id }}" @if((string)($stationId ?? '') === (string)$s->id) selected @endif>{{ $s->name }}</option>
            @endforeach
        </select>
        <input type="date" name="from" value="{{ $from ?? '' }}" class="form-control form-control-sm">
        <input type="date" name="to" value="{{ $to ?? '' }}" class="form-control form-control-sm">
        <button class="btn btn-sm btn-primary">Filter</button>
        <a href="{{ route('admin.pickup-payouts.export', request()->all()) }}" class="btn btn-sm btn-outline-secondary">Export CSV</a>
    </form>
</div>

<div class="card"><div class="card-body">
    <table class="table table-sm">
        <thead>
            <tr>
                <th style="width:60px">#</th>
                <th>Ref</th>
                <th>
                    <a href="?{{ http_build_query(array_merge(request()->except('page'), ['sort'=>'station','dir' => (request('sort')==='station' && request('dir')==='asc') ? 'desc' : 'asc'])) }}">Station
                        @if(request('sort')==='station') @if(request('dir','desc')==='asc') ↑ @else ↓ @endif @endif
                    </a>
                </th>
                <th class="text-end">Amount</th>
                <th>
                    <a href="?{{ http_build_query(array_merge(request()->except('page'), ['sort'=>'date','dir' => (request('sort')==='date' && request('dir')==='asc') ? 'desc' : 'asc'])) }}">Date
                        @if(request('sort')==='date') @if(request('dir','desc')==='asc') ↑ @else ↓ @endif @endif
                    </a>
                </th>
                <th>Status</th>
                <th>Note</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        @foreach($payouts as $p)
            @php $index = ($payouts->firstItem() ?? 0) + $loop->index; @endphp
            <tr>
                <td>{{ $index }}</td>
                <td>{{ $p->reference }}</td>
                <td>{{ $p->station->name ?? '—' }}</td>
                <td class="text-end">₦{{ number_format($p->amount, 2) }}</td>
                <td>{{ $p->created_at->toDateString() }}</td>
                <td>
                    @if($p->is_reversed)
                        <span class="badge bg-danger">Reversed</span>
                    @else
                        <span class="badge bg-success">Paid</span>
                    @endif
                </td>
                <td>{{ Str::limit($p->note ?? '', 80) }}</td>
                <td class="text-end">
                    <a href="#" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#payout-{{ $p->id }}">
                        <i class="bi bi-eye me-1"></i>Details
                    </a>
                    @if(! $p->is_reversed)
                        <form method="post" action="{{ route('admin.pickup-payouts.reverse', $p) }}" style="display:inline-block">
                            @csrf
                            <button class="btn btn-sm btn-outline-danger ms-1" onclick="return confirm('Reverse payout {{ $p->reference }}? This will mark related item fees unpaid.')">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Reverse
                            </button>
                        </form>
                    @endif
                </td>
            </tr>
            <tr class="collapse" id="payout-{{ $p->id }}">
                <td colspan="8">
                    <div class="p-2">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Product</th>
                                    <th>Variant</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-end">Unit Price</th>
                                    <th class="text-end">Commission (10%)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($p->items as $it)
                                    @php
                                        // Find the specific order item that generated this fee
                                        $orderItem = \App\Models\OrderItem::where('order_id', $it->order_id)
                                            ->where('pickup_status', 'picked_up')
                                            ->first();
                                    @endphp
                                    <tr>
                                        <td>{{ $it->order?->reference ?? 'Order #' . $it->order_id }}</td>
                                        <td>{{ $orderItem?->product?->name ?? '—' }}</td>
                                        <td class="text-muted small">{{ $orderItem?->variant?->options_label ?? '—' }}</td>
                                        <td class="text-center">{{ $orderItem?->quantity ?? '—' }}</td>
                                        <td class="text-end">{{ $orderItem ? '₦' . number_format($orderItem->unit_price, 2) : '—' }}</td>
                                        <td class="text-end fw-bold">₦{{ number_format($it->fee_amount, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @if($p->is_reversed)
                            <div class="small text-muted mt-2">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                Reversed by: {{ $p->reversedBy?->name ?? '—' }} at {{ $p->reversed_at?->toDateTimeString() ?? '—' }}
                            </div>
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="mt-3">{{ $payouts->links() }}</div>
</div></div>
@endsection
