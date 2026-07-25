@extends('layouts.pickup-portal', ['title' => 'Payouts — '.session('portal_station_name')])
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Payouts — {{ session('portal_station_name') }}</h4>
    <div>
        <a href="{{ route('pickup-portal.dashboard') }}" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>
</div>

{{-- Payout Summary Card --}}
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-wallet2 me-2"></i>Earnings Summary</h5>
    </div>
    <div class="card-body">
        <div class="row text-center">
            <div class="col-md-3">
                <div class="border rounded p-3 bg-success bg-opacity-10">
                    <div class="small text-muted">Total Earned</div>
                    <div class="fs-4 fw-bold text-success">₦{{ number_format($payoutSummary['total_earned'], 2) }}</div>
                    <div class="small text-muted">{{ $payoutSummary['item_count'] }} item(s) picked up</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3 bg-primary bg-opacity-10">
                    <div class="small text-muted">Total Paid Out</div>
                    <div class="fs-4 fw-bold text-primary">₦{{ number_format($payoutSummary['total_paid_out'], 2) }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3 {{ $payoutSummary['balance_due'] > 0 ? 'bg-warning bg-opacity-10' : 'bg-success bg-opacity-10' }}">
                    <div class="small text-muted">Balance Due (Pending)</div>
                    <div class="fs-4 fw-bold {{ $payoutSummary['balance_due'] > 0 ? 'text-warning' : 'text-success' }}">
                        ₦{{ number_format($payoutSummary['balance_due'], 2) }}
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3">
                    <div class="small text-muted">Commission Rate</div>
                    <div class="fs-4 fw-bold">10%</div>
                    <div class="small text-muted">per item</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Commission Breakdown by Order --}}
@if(! empty($payoutSummary['items']))
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-list-check me-2"></i>Commission Breakdown</h5>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-success" id="selectAllUnpaid">
                <i class="bi bi-check2-all me-1"></i>Select All Unpaid
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="deselectAll">
                <i class="bi bi-x-lg me-1"></i>Deselect All
            </button>
        </div>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('pickup-portal.payouts.markPaid') }}" id="markPaidForm">
            @csrf
            <input type="hidden" name="note" value="">
            @foreach($payoutSummary['items'] as $orderId => $orderData)
                @php
                    $order = $orderData['order'];
                    $allPaid = $orderData['items']->every(fn($i) => $i->pickup_station_fee_paid);
                    $anyUnpaid = $orderData['items']->some(fn($i) => ! $i->pickup_station_fee_paid);
                @endphp
                <div class="card mb-3 {{ $allPaid ? 'border-success' : ($anyUnpaid ? 'border-warning' : '') }}">
                    <div class="card-header d-flex justify-content-between align-items-center {{ $allPaid ? 'bg-success bg-opacity-10' : ($anyUnpaid ? 'bg-warning bg-opacity-10' : '') }}">
                        <div class="d-flex align-items-center gap-2">
                            @if($anyUnpaid)
                                <input type="checkbox" name="order_ids[]" value="{{ $orderId }}"
                                       class="form-check-input payout-order-check" data-order="{{ $orderId }}">
                            @endif
                            <div>
                                <strong>{{ $order->reference }}</strong>
                                <span class="small text-muted ms-2">{{ $order->order_date?->format('M d, Y') }}</span>
                            </div>
                            @if($allPaid)
                                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Paid</span>
                            @else
                                <span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>Pending</span>
                            @endif
                        </div>
                        <div class="fw-bold text-success">
                            Commission: ₦{{ number_format($orderData['commission'], 2) }}
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th style="width:30px"></th>
                                    <th>Product</th>
                                    <th>Variant</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-end">Line Total</th>
                                    <th class="text-end">Commission (10%)</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($orderData['items'] as $item)
                                    <tr class="{{ $item->pickup_station_fee_paid ? 'table-success bg-opacity-10' : '' }}">
                                        <td>
                                            @if(! $item->pickup_station_fee_paid)
                                                <input type="checkbox" name="order_ids[]" value="{{ $orderId }}"
                                                       class="form-check-input payout-order-check" data-order="{{ $orderId }}" style="visibility:hidden">
                                            @endif
                                        </td>
                                        <td>{{ $item->product?->name }}</td>
                                        <td class="text-muted small">{{ $item->variant?->options_label }}</td>
                                        <td class="text-center">{{ $item->quantity }}</td>
                                        <td class="text-end">₦{{ number_format($item->line_total, 2) }}</td>
                                        <td class="text-end fw-bold {{ $item->pickup_station_fee_paid ? 'text-success' : 'text-warning' }}">
                                            ₦{{ number_format($item->commission, 2) }}
                                        </td>
                                        <td class="text-center">
                                            @if($item->pickup_station_fee_paid)
                                                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Paid</span>
                                            @else
                                                <span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>Pending</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach

            <div class="d-flex justify-content-between align-items-center mt-3" id="markPaidSection" style="display:none !important">
                <div class="small text-muted" id="selectedInfo">0 order(s) selected</div>
                <button type="submit" class="btn btn-success" id="markPaidBtn" disabled>
                    <i class="bi bi-cash-stack me-1"></i>Mark Selected as Paid
                </button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- Payout History — DataTable --}}
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Payout History</h5>
        <div class="d-flex gap-2 align-items-center">
            <select id="payout-status" class="form-select form-select-sm" style="width:120px">
                <option value="">All Status</option>
                <option value="paid">Paid</option>
                <option value="reversed">Reversed</option>
            </select>
            <input type="date" id="payout-from" class="form-control form-control-sm" style="width:150px">
            <input type="date" id="payout-to" class="form-control form-control-sm" style="width:150px">
            <button id="payout-filter" class="btn btn-sm btn-primary">Filter</button>
        </div>
    </div>
    <div class="card-body p-0">
        <table id="payouts-table" class="table table-sm mb-0" style="width:100%">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Reference</th>
                    <th>Orders</th>
                    <th>Date</th>
                    <th class="text-end">Amount</th>
                    <th class="text-center">Status</th>
                    <th>Note</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

@endsection

@push('styles')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Select All Unpaid
    document.getElementById('selectAllUnpaid')?.addEventListener('click', function() {
        document.querySelectorAll('.payout-order-check:not(:checked)').forEach(cb => {
            cb.checked = true;
            cb.style.visibility = 'visible';
        });
        updateMarkPaidSection();
    });

    // Deselect All
    document.getElementById('deselectAll')?.addEventListener('click', function() {
        document.querySelectorAll('.payout-order-check:checked').forEach(cb => {
            cb.checked = false;
        });
        updateMarkPaidSection();
    });

    // Individual checkbox change
    document.querySelectorAll('.payout-order-check').forEach(cb => {
        cb.addEventListener('change', updateMarkPaidSection);
    });

    function updateMarkPaidSection() {
        const checked = document.querySelectorAll('.payout-order-check:checked');
        const section = document.getElementById('markPaidSection');
        const info = document.getElementById('selectedInfo');
        const btn = document.getElementById('markPaidBtn');

        if (checked.length > 0) {
            section.style.display = 'flex';
            info.textContent = checked.length + ' order(s) selected';
            btn.disabled = false;
        } else {
            section.style.display = 'none';
            btn.disabled = true;
        }
    }

    // Confirm payout submission
    document.getElementById('markPaidForm')?.addEventListener('submit', function(e) {
        const checked = document.querySelectorAll('.payout-order-check:checked');
        if (checked.length === 0) {
            e.preventDefault();
            return;
        }
        if (!confirm('Mark ' + checked.length + ' order(s) as paid?')) {
            e.preventDefault();
        }
    });

    // Payouts DataTable
    if (document.querySelector('#payouts-table')) {
        var table = $('#payouts-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '{{ route("pickup-portal.payouts.data") }}',
                data: function(d) {
                    d.status = document.getElementById('payout-status')?.value || '';
                    d.from = document.getElementById('payout-from')?.value || '';
                    d.to = document.getElementById('payout-to')?.value || '';
                }
            },
            columns: [
                { data: null, orderable: false, searchable: false, render: function(data, type, row, meta) { return meta.row + meta.settings._iDisplayStart + 1; } },
                { data: 'reference' },
                { data: 'orders', orderable: false, searchable: false },
                { data: 'date' },
                { data: 'amount', className: 'text-end' },
                { data: 'status', className: 'text-center', render: function(data, type, row) { return '<span class="badge ' + row.status_class + '">' + data + '</span>'; } },
                { data: 'note' }
            ],
            order: [[3, 'desc']],
            pageLength: 15,
        });

        document.getElementById('payout-filter')?.addEventListener('click', function() {
            table.ajax.reload();
        });
    }
});
</script>
@endpush
