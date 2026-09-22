@extends('layouts.admin', ['title' => 'Delivery Agents'])
@section('content')
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h3 class="mb-0"><i class="bi bi-truck"></i> Delivery Agents</h3>
        <p class="text-muted mb-0">Manage delivery personnel and their credentials.</p>
    </div>
    <a href="{{ route('admin.delivery-agents.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-circle"></i> Add Agent
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

@if(session('temp_credentials'))
    <div class="modal fade show d-block" id="tempCredsModal" tabindex="-1" style="background:rgba(0,0,0,.5)">
        <div class="modal-dialog"><div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-key me-1"></i>Temporary Credentials</h5>
            </div>
            <div class="modal-body">
                <p class="text-danger fw-bold">Copy these now. They will not be shown again.</p>
                <dl class="row mb-0">
                    <dt class="col-4">Account #</dt>
                    <dd class="col-8 font-monospace fw-bold">{{ session('temp_credentials.account') }}</dd>
                    <dt class="col-4">Email</dt>
                    <dd class="col-8">{{ session('temp_credentials.email') }}</dd>
                    <dt class="col-4">Password</dt>
                    <dd class="col-8 font-monospace fw-bold">{{ session('temp_credentials.password') }}</dd>
                </dl>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" data-bs-dismiss="modal" onclick="document.getElementById('tempCredsModal').remove()">I've copied these</button>
            </div>
        </div></div>
    </div>
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
            <table id="agents-table" class="table table-hover mb-0 align-middle w-100">
                <thead class="table-light">
                    <tr>
                        <th>Account #</th>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Charges</th>
                        <th>Status</th>
                        <th data-dt-no-export class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($agents as $agent)
                    <tr>
                        <td class="font-monospace fw-semibold">{{ $agent->account_number ?? '-' }}</td>
                        <td>{{ $agent->name }}</td>
                        <td>{{ $agent->contact_name ?: '-' }}</td>
                        <td>{{ $agent->phone ?: '-' }}</td>
                        <td>{{ $agent->email ?: '-' }}</td>
                        <td><span class="badge bg-info">{{ $agent->charges_count }}</span></td>
                        <td>
                            @if($agent->is_active)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-secondary">Inactive</span>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('admin.delivery-agents.edit', $agent) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="{{ route('admin.delivery-agents.destroy', $agent) }}" method="post" class="d-inline"
                                  data-confirm="Delete this delivery agent?" data-confirm-title="Delete Agent?"
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
    var table = $('#agents-table').DataTable({
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
                    { extend: 'csv',   className: 'btn btn-sm btn-success',   filename: 'delivery-agents', exportOptions: { columns: ':not([data-dt-no-export])' } },
                    { extend: 'excel', className: 'btn btn-sm btn-success',   filename: 'delivery-agents', exportOptions: { columns: ':not([data-dt-no-export])' } },
                    { extend: 'pdf',   className: 'btn btn-sm btn-danger',    filename: 'delivery-agents', orientation: 'landscape', pageSize: 'A4', exportOptions: { columns: ':not([data-dt-no-export])' } },
                    { extend: 'print', className: 'btn btn-sm btn-secondary', exportOptions: { columns: ':not([data-dt-no-export])' } }
                ]
            },
            topEnd: ['pageLength', 'search']
        }
    });

    $('#filter-status').on('change', function () {
        table.column(6).search(this.value).draw();
    });
});
</script>
@endpush
