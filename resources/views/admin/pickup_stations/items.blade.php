@extends('layouts.admin', ['title' => 'Items — '. $pickupStation->name])
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">
        <i class="bi bi-box-seam me-2"></i>
        Items — {{ $pickupStation->name }}
    </h3>
    <div>
        <a href="{{ route('admin.pickup-stations.index') }}" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>
</div>

{{-- Summary Cards --}}
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-start border-4 border-secondary">
            <div class="card-body text-center">
                <div class="small text-muted">Pending</div>
                <div class="fs-4 fw-bold">{{ $itemsByStatus['pending']->count() }}</div>
                <div class="small text-muted">Awaiting receipt</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-start border-4 border-info">
            <div class="card-body text-center">
                <div class="small text-muted">Received</div>
                <div class="fs-4 fw-bold">{{ $itemsByStatus['received']->count() }}</div>
                <div class="small text-muted">At station</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-start border-4 border-success">
            <div class="card-body text-center">
                <div class="small text-muted">Ready</div>
                <div class="fs-4 fw-bold">{{ $itemsByStatus['ready']->count() }}</div>
                <div class="small text-muted">Awaiting pickup</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-start border-4 border-primary">
            <div class="card-body text-center">
                <div class="small text-muted">Picked Up</div>
                <div class="fs-4 fw-bold">{{ $itemsByStatus['picked_up']->count() }}</div>
                <div class="small text-muted">Commission: ₦{{ number_format($commission['total_commission'], 2) }}</div>
            </div>
        </div>
    </div>
</div>

{{-- Items by Status --}}
@foreach(['pending', 'received', 'ready', 'picked_up'] as $status)
    @php $items = $itemsByStatus[$status]; @endphp
    @if($items->isNotEmpty())
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    @if($status === 'pending')
                        <span class="badge bg-secondary me-2">Pending</span> Items Awaiting Receipt
                    @elseif($status === 'received')
                        <span class="badge bg-info me-2">Received</span> Items at Station
                    @elseif($status === 'ready')
                        <span class="badge bg-success me-2">Ready</span> Items Ready for Pickup
                    @else
                        <span class="badge bg-primary me-2">Picked Up</span> Completed Items
                    @endif
                    <span class="ms-2 small text-muted">({{ $items->count() }} items)</span>
                </h5>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Product</th>
                            <th>Variant</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end">Unit Price</th>
                            <th class="text-end">Commission (10%)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $item)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.orders.show', $item->order_id) }}">
                                        {{ $item->order?->reference }}
                                    </a>
                                </td>
                                <td>{{ $item->order?->customer?->name ?? '—' }}</td>
                                <td>{{ $item->product?->name }}</td>
                                <td class="text-muted small">{{ $item->variant?->options_label }}</td>
                                <td class="text-center">{{ $item->quantity }}</td>
                                <td class="text-end">₦{{ number_format($item->unit_price, 2) }}</td>
                                <td class="text-end text-success fw-bold">
                                    @if($status === 'picked_up')
                                        ₦{{ number_format($item->commission, 2) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ $status === 'pending' ? 'secondary' : ($status === 'received' ? 'info' : ($status === 'ready' ? 'success' : 'primary')) }}">
                                        {{ $item->status_label }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endforeach

@if(collect($itemsByStatus)->every(fn($items) => $items->isEmpty()))
    <div class="alert alert-light text-center py-4">
        <i class="bi bi-inbox fs-2 d-block mb-2 text-muted"></i>
        No items found for this station.
    </div>
@endif

@endsection
