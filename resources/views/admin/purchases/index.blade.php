@extends('layouts.admin', ['title' => 'Purchases'])
@section('content')
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h3 class="mb-0"><i class="bi bi-bag-check"></i> Purchases</h3>
        <p class="text-muted mb-0">Track and manage purchase orders.</p>
    </div>
    <a href="{{ route('admin.purchases.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle"></i> New Purchase
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="row g-2 mb-3">
    <div class="col-md-3">
        <select id="filter-status" class="form-select form-select-sm">
            <option value="">All Statuses</option>
            <option value="pending">Pending</option>
            <option value="received">Received</option>
            <option value="cancelled">Cancelled</option>
        </select>
    </div>
    <div class="col-md-3">
        <select id="filter-supplier" class="form-select form-select-sm">
            <option value="">All Suppliers</option>
            @foreach($suppliers as $supplier)
                <option value="{{ $supplier->name }}">{{ $supplier->name }}</option>
            @endforeach
        </select>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="purchases-table" class="table table-hover mb-0 align-middle w-100">
                <thead class="table-light">
                <tr>
                    <th>Purchase #</th>
                    <th>Date</th>
                    <th>Supplier</th>
                    <th>Products</th>
                    <th>Status</th>
                    <th class="text-end">Total</th>
                    <th data-dt-no-export class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                @foreach($purchases as $p)
                    @php
                        $productNames = $p->items->pluck('product.name')->filter()->unique()->values();
                        $shown = $productNames->take(3);
                        $remaining = $productNames->count() - 3;
                    @endphp
                    <tr>
                        <td class="fw-semibold">{{ $p->display_number }}</td>
                        <td data-order="{{ $p->purchase_date->timestamp }}">{{ $p->purchase_date->format('M d, Y') }}</td>
                        <td>{{ $p->supplier?->name ?? '-' }}</td>
                        <td>
                            @forelse($shown as $name)
                                <span class="badge bg-light text-dark border">{{ $name }}</span>
                            @empty
                                <span class="text-muted">-</span>
                            @endforelse
                            @if($remaining > 0)
                                <span class="badge bg-secondary">+{{ $remaining }} more</span>
                            @endif
                        </td>
                        <td>
                            @if($p->status === 'received')
                                <span class="badge bg-success">Received</span>
                            @elseif($p->status === 'cancelled')
                                <span class="badge bg-danger">Cancelled</span>
                            @else
                                <span class="badge bg-warning text-dark">Pending</span>
                            @endif
                        </td>
                        <td class="text-end" data-order="{{ $p->total_cost }}">&#8358;{{ number_format($p->total_cost, 2) }}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('admin.purchases.show', $p) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i> View
                            </a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function () {
    var table = $('#purchases-table').DataTable({
        order: [[1, 'desc']],
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
        columnDefs: [
            { targets: -1, orderable: false, searchable: false }
        ],
        layout: {
            topStart: {
                buttons: [
                    { extend: 'copy',  className: 'btn btn-sm btn-primary',   exportOptions: { columns: ':not([data-dt-no-export])' } },
                    { extend: 'csv',   className: 'btn btn-sm btn-success',   filename: 'purchases', exportOptions: { columns: ':not([data-dt-no-export])' } },
                    { extend: 'excel', className: 'btn btn-sm btn-success',   filename: 'purchases', exportOptions: { columns: ':not([data-dt-no-export])' } },
                    { extend: 'pdf',   className: 'btn btn-sm btn-danger',    filename: 'purchases', orientation: 'landscape', pageSize: 'A4', exportOptions: { columns: ':not([data-dt-no-export])' } },
                    { extend: 'print', className: 'btn btn-sm btn-secondary', exportOptions: { columns: ':not([data-dt-no-export])' } }
                ]
            },
            topEnd: ['pageLength', 'search']
        }
    });

    $('#filter-status').on('change', function () {
        table.column(4).search(this.value).draw();
    });
    $('#filter-supplier').on('change', function () {
        table.column(2).search(this.value).draw();
    });
});
</script>
@endpush
@endsection
