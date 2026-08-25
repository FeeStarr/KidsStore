@extends('layouts.admin', ['title' => 'Items - '. $pickupStation->name])
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">
        <i class="bi bi-box-seam me-2"></i>
        Items - {{ $pickupStation->name }}
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

{{-- Filters --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label form-label-sm">Status</label>
                <select name="status" id="filterStatus" class="form-select form-select-sm">
                    <option value="all">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="received">Received</option>
                    <option value="ready for pickup">Ready for Pickup</option>
                    <option value="picked_up">Picked Up</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label form-label-sm">From</label>
                <input type="date" name="from" id="filterFrom" class="form-control form-control-sm" value="{{ request('from') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label form-label-sm">To</label>
                <input type="date" name="to" id="filterTo" class="form-control form-control-sm" value="{{ request('to') }}">
            </div>
            <div class="col-md-2">
                <button type="button" id="btnApplyFilters" class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Filter</button>
                <button type="button" id="btnClearFilters" class="btn btn-sm btn-outline-secondary">Clear</button>
            </div>
        </form>
    </div>
</div>

{{-- DataTable --}}
<div class="card">
    <div class="card-body">
        <table id="itemsTable" class="table table-sm" style="width:100%">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Product</th>
                    <th>Variant</th>
                    <th class="text-center">Qty</th>
                    <th class="text-end">Unit Price</th>
                    <th class="text-end">Commission ({{ \App\Models\Setting::get('commission_rate', 10) }}%)</th>
                    <th>Status</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

@endsection

@push('scripts')
<script>
$(function () {
    var table = $('#itemsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("admin.pickup-stations.items.data", $pickupStation) }}',
            data: function (d) {
                d.status = $('#filterStatus').val();
                d.from = $('#filterFrom').val();
                d.to = $('#filterTo').val();
            }
        },
        columns: [
            { data: 'order_reference', name: 'order' },
            { data: 'customer', name: 'customer' },
            { data: 'product', name: 'product' },
            { data: 'variant', name: 'variant' },
            { data: 'quantity', className: 'text-center', orderable: false },
            { data: 'unit_price', className: 'text-end', orderable: false },
            { data: 'commission', className: 'text-end', orderable: false },
            { data: 'status', orderable: false },
        ],
        order: [[0, 'desc']],
        pageLength: 25,
    });

    $('#btnApplyFilters').on('click', function () { table.ajax.reload(); });

    $('#btnClearFilters').on('click', function () {
        $('#filterStatus').val('all');
        $('#filterFrom').val('');
        $('#filterTo').val('');
        table.ajax.reload();
    });
});
</script>
@endpush
