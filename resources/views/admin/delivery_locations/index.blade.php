@extends('layouts.admin', ['title' => 'Delivery Locations'])
@section('content')
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h3 class="mb-0"><i class="bi bi-geo-alt-fill"></i> Delivery Locations</h3>
        <p class="text-muted mb-0">Manage delivery zones and areas.</p>
    </div>
    <a href="{{ route('admin.delivery-locations.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle"></i> Add Location
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
            <option value="Active">Active</option>
            <option value="Inactive">Inactive</option>
        </select>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="locations-table" class="table table-hover mb-0 align-middle w-100">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>State</th>
                        <th>Description</th>
                        <th>Charges</th>
                        <th>Status</th>
                        <th data-dt-no-export class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($locations as $loc)
                    <tr>
                        <td class="fw-semibold">{{ $loc->name }}</td>
                        <td>{{ $loc->state ?: '-' }}</td>
                        <td>{{ $loc->description ? Str::limit($loc->description, 50) : '-' }}</td>
                        <td><span class="badge bg-info">{{ $loc->charges_count }}</span></td>
                        <td>
                            @if($loc->is_active)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-secondary">Inactive</span>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('admin.delivery-locations.edit', $loc) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="{{ route('admin.delivery-locations.destroy', $loc) }}" method="post" class="d-inline"
                                  data-confirm="Delete this delivery location?" data-confirm-title="Delete Location?"
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
    var table = $('#locations-table').DataTable({
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
                    { extend: 'csv',   className: 'btn btn-sm btn-success',   filename: 'delivery-locations', exportOptions: { columns: ':not([data-dt-no-export])' } },
                    { extend: 'excel', className: 'btn btn-sm btn-success',   filename: 'delivery-locations', exportOptions: { columns: ':not([data-dt-no-export])' } },
                    { extend: 'pdf',   className: 'btn btn-sm btn-danger',    filename: 'delivery-locations', orientation: 'landscape', pageSize: 'A4', exportOptions: { columns: ':not([data-dt-no-export])' } },
                    { extend: 'print', className: 'btn btn-sm btn-secondary', exportOptions: { columns: ':not([data-dt-no-export])' } }
                ]
            },
            topEnd: ['pageLength', 'search']
        }
    });

    $('#filter-status').on('change', function () {
        table.column(4).search(this.value).draw();
    });
});
</script>
@endpush
