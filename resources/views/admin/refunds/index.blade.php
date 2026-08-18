@extends('layouts.admin', ['title' => 'Return Requests'])
@section('content')

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Return Requests
        @if($pending)
            <span class="badge bg-danger ms-2">{{ $pending }} pending</span>
        @endif
    </h3>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="card">
    <table id="refunds-table" class="table mb-0 align-middle w-100">
        <thead class="table-light">
            <tr>
                <th>Request</th>
                <th>Order</th>
                <th>Customer</th>
                <th>Scope</th>
                <th>Reason</th>
                <th class="text-end">Amount</th>
                <th>Status</th>
                <th>Submitted</th>
                <th data-dt-no-export></th>
            </tr>
        </thead>
        <tbody>
        @foreach($requests as $r)
            @php
                $badge = match($r->status) {
                    'requested', 'pending_review'    => 'bg-warning text-dark',
                    'awaiting_evidence'              => 'bg-warning text-dark',
                    'approved', 'awaiting_shipment', 'in_transit' => 'bg-primary',
                    'received', 'inspection'         => 'bg-secondary',
                    'refund_approved', 'refund_processing' => 'bg-info',
                    'refunded', 'completed', 'replacement_delivered' => 'bg-success',
                    'rejected', 'cancelled'          => 'bg-danger',
                    'refund_failed'                  => 'bg-dark',
                    default                          => 'bg-secondary',
                };
            @endphp
            <tr>
                <td class="font-monospace small">#{{ $r->id }}</td>
                <td>
                    <a href="{{ route('admin.orders.show', $r->order) }}">{{ $r->order->reference }}</a>
                </td>
                <td>{{ $r->order->customer?->name ?? '—' }}</td>
                <td class="small">{{ $r->getScopeLabel() }}</td>
                <td class="small">{{ $r->reason_label }}</td>
                <td class="text-end">₦{{ number_format($r->amount, 2) }}</td>
                <td><span class="badge {{ $badge }}">{{ ucfirst(str_replace('_', ' ', $r->status)) }}</span></td>
                <td data-order="{{ $r->created_at->timestamp }}" class="small text-muted">
                    {{ $r->created_at->format('M d, Y g:i A') }}
                </td>
                <td class="text-end">
                    <a href="{{ route('admin.refunds.show', $r) }}" class="btn btn-sm btn-outline-secondary">Review</a>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

@push('scripts')
<script>
$(function () {
    $('#refunds-table').DataTable({
        order: [[7, 'desc']],
        pageLength: 25,
        columnDefs: [{ targets: -1, orderable: false, searchable: false }],
        layout: {
            topStart: {
                buttons: [
                    { extend: 'csv',   className: 'btn btn-sm btn-success',   filename: 'return-requests', exportOptions: { columns: ':not([data-dt-no-export])' } },
                    { extend: 'excel', className: 'btn btn-sm btn-success',   filename: 'return-requests', exportOptions: { columns: ':not([data-dt-no-export])' } },
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
