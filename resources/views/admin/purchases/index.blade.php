@extends('layouts.admin', ['title' => 'Purchases'])
@section('content')
<div class="d-flex justify-content-between mb-3">
    <h3>Purchases</h3>
    <a href="{{ route('admin.purchases.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New Purchase</a>
</div>

<div class="card"><div class="card-body">
<table id="purchases-table" class="table align-middle w-100">
    <thead>
    <tr>
        <th>Purchase Number</th>
        <th>Date</th>
        <th>Supplier</th>
        <th>Status</th>
        <th class="text-end">Total (NGN)</th>
        <th data-dt-no-export class="text-end">Actions</th>
    </tr>
    </thead>
    <tbody>
    @foreach($purchases as $p)
        <tr>
            <td>{{ $p->display_number }}</td>
            <td>{{ $p->purchase_date->format('Y-m-d') }}</td>
            <td>{{ $p->supplier?->name ?? '-' }}</td>
            <td>{{ $p->status }}</td>
            <td class="text-end">{{ number_format($p->total_cost, 2) }}</td>
            <td class="text-end text-nowrap">
                <a href="{{ route('admin.purchases.show', $p) }}" class="btn btn-sm btn-outline-secondary">View</a>
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
</div></div>

@push('scripts')
<script>
$(function () {
    $('#purchases-table').DataTable({
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
                    { extend: 'csv',   className: 'btn btn-sm btn-success',   filename: 'purchases', exportOptions: { columns: ':not([data-dt-no-export])' } },
                    { extend: 'excel', className: 'btn btn-sm btn-success',   filename: 'purchases', exportOptions: { columns: ':not([data-dt-no-export])' } },
                    { extend: 'pdf',   className: 'btn btn-sm btn-danger',    filename: 'purchases', orientation: 'landscape', pageSize: 'A4', exportOptions: { columns: ':not([data-dt-no-export])' } },
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
