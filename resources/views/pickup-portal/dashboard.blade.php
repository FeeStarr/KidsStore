@extends('layouts.pickup-portal', ['title' => 'Dashboard — '.session('portal_station_name')])
@section('content')

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <i class="bi bi-box-seam me-2"></i>
        {{ session('portal_station_name') }}
    </h4>
    <div class="d-flex gap-2">
        <a href="{{ route('pickup-portal.dashboard', ['filter' => 'pending']) }}"
           class="btn btn-sm {{ $filter === 'pending' ? 'btn-warning' : 'btn-outline-secondary' }}">
            Pending
            @if($counts['pending']) <span class="badge bg-dark ms-1">{{ $counts['pending'] }}</span> @endif
        </a>
        <a href="{{ route('pickup-portal.dashboard', ['filter' => 'received']) }}"
           class="btn btn-sm {{ $filter === 'received' ? 'btn-info' : 'btn-outline-secondary' }}">
            Received
            @if($counts['received']) <span class="badge bg-dark ms-1">{{ $counts['received'] }}</span> @endif
        </a>
        <a href="{{ route('pickup-portal.dashboard', ['filter' => 'ready']) }}"
           class="btn btn-sm {{ $filter === 'ready' ? 'btn-success' : 'btn-outline-secondary' }}">
            Ready for Pickup
            @if($counts['ready']) <span class="badge bg-dark ms-1">{{ $counts['ready'] }}</span> @endif
        </a>
        <a href="{{ route('pickup-portal.dashboard', ['filter' => 'picked_up']) }}"
           class="btn btn-sm {{ $filter === 'picked_up' ? 'btn-primary' : 'btn-outline-secondary' }}">
            Picked Up
            @if($counts['picked_up']) <span class="badge bg-dark ms-1">{{ $counts['picked_up'] }}</span> @endif
        </a>
    </div>
</div>

@if(! $station->is_available)
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <strong>Station Unavailable:</strong> {{ $station->unavailability_reason ?? 'This station is currently not accepting orders.' }}
    </div>
@endif

@if($filter === 'picked_up')
    {{-- Picked Up Tab — DataTable --}}
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-check-circle me-2"></i>Picked Up Items</h5>
            <div class="d-flex gap-2 align-items-center">
                <input type="date" id="picked-up-from" class="form-control form-control-sm" style="width:150px" placeholder="From">
                <input type="date" id="picked-up-to" class="form-control form-control-sm" style="width:150px" placeholder="To">
                <button id="picked-up-filter" class="btn btn-sm btn-primary">Filter</button>
                <a href="{{ route('pickup-portal.picked-up.export') }}" class="btn btn-sm btn-outline-success">
                    <i class="bi bi-download me-1"></i>Export CSV
                </a>
            </div>
        </div>
        <div class="card-body p-0">
            <table id="picked-up-table" class="table table-sm mb-0" style="width:100%">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Product</th>
                        <th>Variant</th>
                        <th class="text-center">Qty</th>
                        <th class="text-end">Line Total</th>
                        <th class="text-end">Commission (10%)</th>
                        <th>Picked Up At</th>
                    </tr>
                </thead>
                <tbody></tbody>
                <tfoot>
                    <tr class="table-light fw-bold">
                        <td colspan="6" class="text-end">Total Commission:</td>
                        <td class="text-end" id="total-line-total">—</td>
                        <td class="text-end text-success" id="total-commission">—</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

@elseif(in_array($filter, ['pending', 'received']))
    {{-- Pending/Received — Bulk Action Cards --}}
    @if($currentItems->isEmpty())
        <div class="alert alert-light text-center py-4">
            <i class="bi bi-inbox fs-2 d-block mb-2 text-muted"></i>
            No {{ $filter }} items at this station.
        </div>
    @else
        <form method="POST" action="{{ route('pickup-portal.bulk.'.($filter === 'pending' ? 'received' : 'ready')) }}">
            @csrf
            <div class="mb-2 d-flex gap-2 align-items-center">
                <button class="btn btn-sm btn-primary" type="submit">
                    Mark Selected as {{ $filter === 'pending' ? 'Received' : 'Ready for Pickup' }}
                </button>
                <span class="small text-muted">Select items below to bulk update</span>
            </div>

            @foreach($currentItems->groupBy('order_id') as $orderId => $items)
                @php $order = $items->first()->order; @endphp
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <strong>{{ $order->reference }}</strong>
                            <span class="small text-muted ms-2">Ordered {{ $order->order_date?->format('M d, Y') }}</span>
                        </div>
                        <div class="small">
                            Customer: {{ $order->customer?->name ?? '—' }}
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th width="40"><input type="checkbox" class="select-all"></th>
                                    <th>Product</th>
                                    <th>Variant</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-end">Price</th>
                                    <th class="text-end">Commission (10%)</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($items as $item)
                                    <tr>
                                        <td><input type="checkbox" name="item_ids[]" value="{{ $item->id }}" class="item-checkbox"></td>
                                        <td>{{ $item->product?->name }}</td>
                                        <td class="text-muted small">{{ $item->variant?->options_label }}</td>
                                        <td class="text-center">{{ $item->quantity }}</td>
                                        <td class="text-end">₦{{ number_format($item->line_total, 2) }}</td>
                                        <td class="text-end text-success">₦{{ number_format($item->line_total * 0.10, 2) }}</td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary">{{ $item->status_label }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </form>
    @endif

@elseif($filter === 'ready')
    {{-- Ready for Pickup — Individual Action Cards --}}
    @if($currentItems->isEmpty())
        <div class="alert alert-light text-center py-4">
            <i class="bi bi-inbox fs-2 d-block mb-2 text-muted"></i>
            No items ready for pickup at this station.
        </div>
    @else
        @foreach($currentItems->groupBy('order_id') as $orderId => $items)
            @php
                $order = $items->first()->order;
                $isPaid = $order->payment_status === 'paid';
            @endphp
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <strong>{{ $order->reference }}</strong>
                        <span class="small text-muted ms-2">Ordered {{ $order->order_date?->format('M d, Y') }}</span>
                        @if($isPaid)
                            <span class="badge bg-success ms-2"><i class="bi bi-check-circle me-1"></i>Paid</span>
                        @else
                            <span class="badge bg-warning text-dark ms-2"><i class="bi bi-exclamation-circle me-1"></i>Unpaid</span>
                        @endif
                    </div>
                    <div class="small">
                        Customer: {{ $order->customer?->name ?? '—' }}
                        @if($order->customer?->phone)
                            <span class="ms-2"><i class="bi bi-telephone me-1"></i>{{ $order->customer->phone }}</span>
                        @endif
                    </div>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Variant</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Price</th>
                                <th class="text-end">Commission (10%)</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $item)
                                <tr>
                                    <td>{{ $item->product?->name }}</td>
                                    <td class="text-muted small">{{ $item->variant?->options_label }}</td>
                                    <td class="text-center">{{ $item->quantity }}</td>
                                    <td class="text-end">₦{{ number_format($item->line_total, 2) }}</td>
                                    <td class="text-end text-success">₦{{ number_format($item->line_total * 0.10, 2) }}</td>
                                    <td class="text-center">
                                        @if($isPaid)
                                            <form method="POST" action="{{ route('pickup-portal.items.picked-up', $item) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-success confirm-pickup-btn"
                                                        data-item="{{ $item->product?->name }}">
                                                    <i class="bi bi-check-circle me-1"></i>Picked Up
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-danger small">
                                                <i class="bi bi-lock me-1"></i>Payment pending
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    @endif

@else
    <div class="alert alert-light text-center py-4">
        <i class="bi bi-inbox fs-2 d-block mb-2 text-muted"></i>
        No items at this station.
    </div>
@endif

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Select all checkbox
    document.querySelectorAll('.select-all').forEach(function(el) {
        el.addEventListener('change', function() {
            const checked = this.checked;
            this.closest('table').querySelectorAll('.item-checkbox').forEach(function(cb) {
                cb.checked = checked;
            });
        });
    });

    // Picked Up DataTable
    if (document.querySelector('#picked-up-table')) {
        let totalLineTotal = 0;
        let totalCommission = 0;

        const table = $('#picked-up-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '{{ route("pickup-portal.picked-up.data") }}',
                data: function(d) {
                    d.from = document.getElementById('picked-up-from')?.value || '';
                    d.to = document.getElementById('picked-up-to')?.value || '';
                }
            },
            columns: [
                { data: null, orderable: false, searchable: false, render: function(data, type, row, meta) { return meta.row + meta.settings._iDisplayStart + 1; } },
                { data: 'order_reference' },
                { data: 'customer' },
                { data: 'product' },
                { data: 'variant' },
                { data: 'quantity', className: 'text-center' },
                { data: 'line_total', className: 'text-end' },
                { data: 'commission', className: 'text-end text-success fw-bold' },
                { data: 'picked_up_at' }
            ],
            order: [[8, 'desc']],
            pageLength: 25,
            drawCallback: function(settings) {
                const api = this.api();
                let lineTotal = 0;
                let commission = 0;
                api.column(6, { page: 'current' }).data().each(function(val) {
                    lineTotal += parseFloat(val.replace(/[₦,]/g, '')) || 0;
                });
                api.column(7, { page: 'current' }).data().each(function(val) {
                    commission += parseFloat(val.replace(/[₦,]/g, '')) || 0;
                });
                $('#total-line-total').text('₦' + lineTotal.toLocaleString(undefined, {minimumFractionDigits: 2}));
                $('#total-commission').text('₦' + commission.toLocaleString(undefined, {minimumFractionDigits: 2}));
            }
        });

        // Filter button
        document.getElementById('picked-up-filter')?.addEventListener('click', function() {
            table.ajax.reload();
        });
    }

    // Confirm pickup SweetAlert
    document.querySelectorAll('.confirm-pickup-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const form = this.closest('form');
            const itemName = this.dataset.item;
            Swal.fire({
                title: 'Confirm Pickup',
                html: `<p>Has the customer collected <strong>${itemName}</strong>?</p>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, confirm',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#198754',
            }).then(function(result) {
                if (result.isConfirmed) form.submit();
            });
        });
    });
});
</script>
@endpush
@endsection
