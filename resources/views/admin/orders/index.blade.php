@extends('layouts.admin', ['title' => 'Orders'])
@section('content')
<div class="d-flex justify-content-between mb-3">
    <h3>Orders</h3>
    <a href="{{ route('admin.orders.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New Order</a>
</div>

<div class="card"><div class="card-body">
<table id="orders-table" class="table align-middle w-100">
    <thead>
    <tr>
        <th>Reference</th>
        <th>Date</th>
        <th>Customer</th>
        <th>Status</th>
        <th>Payment</th>
        <th class="text-end">Total (NGN)</th>
        <th data-dt-no-export class="text-end">Actions</th>
    </tr>
    </thead>
    <tbody>
    @foreach($orders as $o)
        <tr>
            <td>{{ $o->reference }}</td>
            <td>{{ $o->order_date->format('Y-m-d') }}</td>
            <td>{{ $o->customer?->name ?? '-' }}</td>
            <td><span class="badge {{ match($o->status) {
                'delivered' => 'text-bg-success',
                'cancelled' => 'text-bg-danger',
                'ready for pick up' => 'text-bg-warning text-dark',
                'confirmed' => 'text-bg-primary',
                default => 'text-bg-secondary'
            } }}">{{ $o->getStatusLabel() }}</span></td>
            <td><span class="badge text-bg-light">{{ ucfirst($o->payment_status) }}</span></td>
            <td class="text-end">{{ number_format($o->total_amount, 2) }}</td>
            <td class="text-end text-nowrap">
                <a href="{{ route('admin.orders.show', $o) }}" class="btn btn-sm btn-outline-secondary">View</a>
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
</div></div>

@push('scripts')
<script>
$(function () {
    $('#orders-table').DataTable({
        order: [[1, 'desc']],
        pageLength: 15,
        lengthMenu: [[10, 15, 25, 50, 100, -1], [10, 15, 25, 50, 100, 'All']],
        columnDefs: [
            { targets: -1, orderable: false, searchable: false }
        ],
        layout: {
            topStart: {
                buttons: [
                    { extend: 'copy',  className: 'btn btn-sm btn-primary',   exportOptions: { columns: ':not([data-dt-no-export])' } },
                    { extend: 'csv',   className: 'btn btn-sm btn-success',   filename: 'orders', exportOptions: { columns: ':not([data-dt-no-export])' } },
                    { extend: 'excel', className: 'btn btn-sm btn-success',   filename: 'orders', exportOptions: { columns: ':not([data-dt-no-export])' } },
                    { extend: 'pdf',   className: 'btn btn-sm btn-danger',    filename: 'orders', orientation: 'landscape', pageSize: 'A4', exportOptions: { columns: ':not([data-dt-no-export])' } },
                    { extend: 'print', className: 'btn btn-sm btn-secondary', exportOptions: { columns: ':not([data-dt-no-export])' } }
                ]
            },
            topEnd: ['pageLength', 'search']
        }
    });
});
</script>
@endpush
@endsection
