@extends('layouts.admin', ['title' => 'Delivery Charges'])
@section('content')
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h3 class="mb-0"><i class="bi bi-currency-dollar"></i> Delivery Charges</h3>
        <p class="text-muted mb-0">Manage delivery fees per agent and location.</p>
    </div>
    <a href="{{ route('admin.delivery-charges.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle"></i> Add Charge
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
        <select id="filter-agent" class="form-select form-select-sm">
            <option value="">All Agents</option>
            @foreach($agents as $agent)
                <option value="{{ $agent->name }}">{{ $agent->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <select id="filter-location" class="form-select form-select-sm">
            <option value="">All Locations</option>
            @foreach($locations as $loc)
                <option value="{{ $loc->name }}">{{ $loc->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <select id="filter-status" class="form-select form-select-sm">
            <option value="">All Statuses</option>
            <option value="Active">Active</option>
            <option value="Inactive">Inactive</option>
        </select>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="charges-table" class="table table-hover mb-0 align-middle w-100">
                <thead class="table-light">
                    <tr>
                        <th>Agent</th>
                        <th>Location</th>
                        <th>Amount</th>
                        <th>Effective From</th>
                        <th>Status</th>
                        <th data-dt-no-export class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($charges as $charge)
                    <tr>
                        <td class="fw-semibold">{{ $charge->agent->name ?? '-' }}</td>
                        <td>{{ $charge->location->name ?? '-' }}</td>
                        <td data-order="{{ $charge->amount }}">&#8358;{{ number_format($charge->amount, 2) }}</td>
                        <td data-order="{{ $charge->effective_from?->timestamp ?? 0 }}">
                            {{ $charge->effective_from?->format('M d, Y') ?? '-' }}
                        </td>
                        <td>
                            @if($charge->is_active)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-secondary">Inactive</span>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('admin.delivery-charges.edit', $charge) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="{{ route('admin.delivery-charges.destroy', $charge) }}" method="post" class="d-inline"
                                  data-confirm="Delete this delivery charge?" data-confirm-title="Delete Charge?"
                                  data-confirm-yes="Yes, delete">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
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
    var table = $('#charges-table').DataTable({
        order: [[0, 'asc']],
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
        columnDefs: [
            { targets: -1, orderable: false, searchable: false }
        ],
        layout: {
            topStart: {
                buttons: [
                    { extend: 'copy',  className: 'btn btn-sm btn-primary',   exportOptions: { columns: ':not([data-dt-no-export])' } },
                    { extend: 'csv',   className: 'btn btn-sm btn-success',   filename: 'delivery-charges', exportOptions: { columns: ':not([data-dt-no-export])' } },
                    { extend: 'excel', className: 'btn btn-sm btn-success',   filename: 'delivery-charges', exportOptions: { columns: ':not([data-dt-no-export])' } },
                    { extend: 'pdf',   className: 'btn btn-sm btn-danger',    filename: 'delivery-charges', orientation: 'landscape', pageSize: 'A4', exportOptions: { columns: ':not([data-dt-no-export])' } },
                    { extend: 'print', className: 'btn btn-sm btn-secondary', exportOptions: { columns: ':not([data-dt-no-export])' } }
                ]
            },
            topEnd: ['pageLength', 'search']
        }
    });

    $('#filter-agent').on('change', function () {
        table.column(0).search(this.value).draw();
    });
    $('#filter-location').on('change', function () {
        table.column(1).search(this.value).draw();
    });
    $('#filter-status').on('change', function () {
        table.column(4).search(this.value).draw();
    });
});
</script>
@endpush
