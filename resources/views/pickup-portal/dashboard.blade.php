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
        <a href="{{ route('pickup-portal.dashboard', ['filter' => 'returns']) }}"
           class="btn btn-sm {{ $filter === 'returns' ? 'btn-danger' : 'btn-outline-secondary' }}">
            <i class="bi bi-arrow-counterclockwise me-1"></i>Returns
            @if($counts['returns']) <span class="badge bg-dark ms-1">{{ $counts['returns'] }}</span> @endif
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
    {{-- Pending/Received — Individual Action Cards --}}
    @if($currentItems->isEmpty())
        <div class="alert alert-light text-center py-4">
            <i class="bi bi-inbox fs-2 d-block mb-2 text-muted"></i>
            No {{ $filter }} items at this station.
        </div>
    @else
        {{-- Bulk action bar --}}
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
                            <input type="checkbox" class="select-all me-2">
                            <strong>{{ $order->reference }}</strong>
                            <span class="small text-muted ms-2">Ordered {{ $order->order_date?->format('M d, Y') }}</span>
                        </div>
                    <div class="small d-flex gap-2 align-items-center">
                        <span>Customer: {{ $order->customer?->name ?? '—' }}</span>
                        @if($order->customer)
                            <form method="POST" action="{{ route('pickup-portal.send-reminder', $order) }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-info py-0" title="Send pickup reminder to customer">
                                    <i class="bi bi-bell me-1"></i>Remind
                                </button>
                            </form>
                        @endif
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
                                    <th class="text-center">Action</th>
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
                                            @if($filter === 'received')
                                                <form method="POST" action="{{ route('pickup-portal.items.ready', $item) }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-success ms-1 mark-ready-btn"
                                                            data-item="{{ $item->product?->name }}">
                                                        <i class="bi bi-bell me-1"></i>Ready
                                                    </button>
                                                </form>
                                            @endif
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
                $balance = ($order->grand_total ?? 0) - ($order->amount_paid ?? 0);
            @endphp
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <strong>{{ $order->reference }}</strong>
                        <span class="small text-muted ms-2">Ordered {{ $order->order_date?->format('M d, Y') }}</span>
                        @if($isPaid)
                            <span class="badge bg-success ms-2"><i class="bi bi-check-circle me-1"></i>Paid</span>
                        @else
                            <span class="badge bg-warning text-dark ms-2"><i class="bi bi-exclamation-circle me-1"></i>Unpaid — ₦{{ number_format($balance, 2) }} remaining</span>
                        @endif
                    </div>
                    <div class="small">
                        Customer: {{ $order->customer?->name ?? '—' }}
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
                                            <span class="text-danger small me-2">
                                                <i class="bi bi-lock me-1"></i>Payment pending
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{-- Order-level actions for unpaid orders --}}
                @if(! $isPaid)
                <div class="card-footer bg-light">
                    <div class="d-flex gap-2 align-items-center flex-wrap">
                        <span class="small text-muted me-1">Collect payment:</span>
                        <button type="button" class="btn btn-sm btn-outline-primary"
                                data-bs-toggle="modal" data-bs-target="#transferModal"
                                data-order-id="{{ $order->id }}"
                                data-order-ref="{{ $order->reference }}"
                                data-amount="{{ number_format($balance, 2) }}"
                                data-amount-raw="{{ $balance }}"
                                data-customer="{{ $order->customer?->name ?? 'Customer' }}">
                            <i class="bi bi-bank me-1"></i>Pay by Transfer
                        </button>
                        <form method="POST" action="{{ route('pickup-portal.record-payment', $order) }}" class="d-inline" id="cash-form-{{ $order->id }}">
                            @csrf
                            <input type="hidden" name="amount" value="{{ $balance }}">
                            <input type="hidden" name="method" value="cash">
                            <button type="submit" class="btn btn-sm btn-outline-success"
                                    onclick="return confirm('Record cash payment of ₦{{ number_format($balance, 2) }}?')">
                                <i class="bi bi-cash me-1"></i>Cash
                            </button>
                        </form>
                    </div>
                </div>
                @endif
            </div>
        @endforeach
    @endif

@elseif($filter === 'returns')
    {{-- Returns Awaiting Collection --}}
    @if($currentItems->isEmpty())
        <div class="alert alert-light text-center py-4">
            <i class="bi bi-inbox fs-2 d-block mb-2 text-muted"></i>
            No pending returns at this station.
        </div>
    @else
        @foreach($currentItems as $rr)
            @php
                $order = $rr->order;
                $item = $rr->orderItem;
                $isPaid = $order->payment_status === 'paid';
            @endphp
            <div class="card mb-3 border-warning">
                <div class="card-header bg-warning bg-opacity-10 d-flex justify-content-between align-items-center">
                    <div>
                        <strong><i class="bi bi-arrow-counterclockwise me-1"></i>Return — {{ $order->reference }}</strong>
                        <span class="small text-muted ms-2">Approved {{ $rr->reviewed_at?->format('M d, Y') }}</span>
                    </div>
                    <div class="small">
                        Customer: {{ $order->customer?->name ?? '—' }}
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <table class="table table-sm mb-2">
                                <tr>
                                    <td class="text-muted" style="width:140px">Item</td>
                                    <td><strong>{{ $item?->product?->name ?? 'N/A' }}</strong> @if($item?->variant?->options_label) — {{ $item->variant->options_label }} @endif</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Quantity</td>
                                    <td>{{ $rr->quantity }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Reason</td>
                                    <td><span class="badge bg-warning text-dark">{{ $rr->reason_label }}</span></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Refund Amount</td>
                                    <td class="fw-bold text-success">₦{{ number_format($rr->amount, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Payment Status</td>
                                    <td>
                                        @if($isPaid)
                                            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Paid</span>
                                        @else
                                            <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-circle me-1"></i>Unpaid</span>
                                        @endif
                                    </td>
                                </tr>
                                @if($rr->details)
                                <tr>
                                    <td class="text-muted">Customer Notes</td>
                                    <td>{{ $rr->details }}</td>
                                </tr>
                                @endif
                            </table>
                        </div>
                        <div class="col-md-4 text-center">
                            @if($item?->product?->image_url)
                                <img src="{{ $item->product->image_url }}" alt="" class="img-thumbnail mb-2" style="max-height:100px;">
                            @endif
                            <div>
                                <a href="{{ route('pickup-portal.returns.show', $rr) }}" class="btn btn-sm btn-outline-warning mb-1">
                                    <i class="bi bi-eye me-1"></i>View Details
                                </a>
                                <form method="POST" action="{{ route('pickup-portal.returns.collect', $rr) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-success collect-return-btn"
                                            data-item="{{ $item?->product?->name ?? 'this item' }}">
                                        <i class="bi bi-check-circle me-1"></i>Mark Collected
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
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

    // Mark ready SweetAlert
    document.querySelectorAll('.mark-ready-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const form = this.closest('form');
            const itemName = this.dataset.item;
            Swal.fire({
                title: 'Mark as Ready for Pickup',
                html: `<p>Mark <strong>${itemName}</strong> as ready? The customer will be notified.</p>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, mark ready',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#198754',
            }).then(function(result) {
                if (result.isConfirmed) form.submit();
            });
        });
    });

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

    // Collect return SweetAlert
    document.querySelectorAll('.collect-return-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const form = this.closest('form');
            const itemName = this.dataset.item;
            Swal.fire({
                title: 'Confirm Return Collection',
                html: `<p>Has the customer brought <strong>${itemName}</strong> for return?</p><p class="text-muted small">Admin and customer care will be notified.</p>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, collected',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#198754',
            }).then(function(result) {
                if (result.isConfirmed) form.submit();
            });
        });
    });

    // Transfer modal — populate from button data
    const transferModal = document.getElementById('transferModal');
    if (transferModal) {
        transferModal.addEventListener('show.bs.modal', function(event) {
            const btn = event.relatedTarget;
            const orderId = btn.dataset.orderId;
            document.getElementById('transfer-order-id').value = orderId;
            document.getElementById('transfer-order-ref').textContent = btn.dataset.orderRef;
            document.getElementById('transfer-amount').textContent = '₦' + btn.dataset.amount;
            document.getElementById('transfer-customer').textContent = btn.dataset.customer;
            document.getElementById('transfer-pay-amount').value = btn.dataset.amountRaw || '';
            // Set form action dynamically with the order ID
            const form = document.getElementById('transfer-confirm-form');
            form.action = '{{ url("/pickup-portal/orders") }}/' + orderId + '/record-payment';
        });
    }
});
</script>
@endpush

{{-- Transfer Payment Modal --}}
<div class="modal fade" id="transferModal" tabindex="-1" aria-labelledby="transferModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="transferModalLabel"><i class="bi bi-bank me-2"></i>Pay by Transfer</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">Share these details with the customer for bank transfer payment. Verification is manual — confirm payment received below.</p>

                @if($bankAccount)
                    <div class="card border-primary mb-3">
                        <div class="card-body text-center py-4">
                            <div class="small text-muted mb-1">Bank Name</div>
                            <div class="fs-5 fw-bold mb-3">{{ $bankAccount->bank_name }}</div>

                            <div class="small text-muted mb-1">Account Number</div>
                            <div class="fs-4 fw-bold text-primary font-monospace mb-3" style="letter-spacing:2px;">{{ $bankAccount->bank_account_number }}</div>

                            <div class="small text-muted mb-1">Account Name</div>
                            <div class="fs-6 fw-semibold">{{ $bankAccount->bank_account_name }}</div>

                            @if($bankAccount->instructions)
                                <div class="mt-3 p-2 bg-light rounded small text-muted">
                                    <i class="bi bi-info-circle me-1"></i>{{ $bankAccount->instructions }}
                                </div>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        No bank account configured. Please ask admin to add one in <strong>Settings > Bank Accounts</strong>.
                    </div>
                @endif

                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="small text-muted">Order</span>
                        <strong id="transfer-order-ref">—</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="small text-muted">Customer</span>
                        <span id="transfer-customer">—</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="small text-muted">Amount Due</span>
                        <span class="fs-5 fw-bold text-success" id="transfer-amount">—</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                @if($bankAccount)
                    <form method="POST" action="#" id="transfer-confirm-form">
                        @csrf
                        <input type="hidden" name="amount" id="transfer-pay-amount" value="">
                        <input type="hidden" name="method" value="transfer">
                        <input type="hidden" name="note" value="Paid via bank transfer at pickup station">
                        <input type="hidden" name="order_id" id="transfer-order-id" value="">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-circle me-1"></i>Confirm Payment Received
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
