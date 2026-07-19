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
                <div class="border rounded p-3">
                    <div class="small text-muted">Total Earned</div>
                    <div class="fs-4 fw-bold text-success">₦{{ number_format($payoutSummary['total_earned'], 2) }}</div>
                    <div class="small text-muted">{{ $payoutSummary['item_count'] }} item(s) picked up</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3">
                    <div class="small text-muted">Total Paid Out</div>
                    <div class="fs-4 fw-bold text-primary">₦{{ number_format($payoutSummary['total_paid_out'], 2) }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3">
                    <div class="small text-muted">Balance Due</div>
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
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-list-check me-2"></i>Commission Breakdown</h5>
    </div>
    <div class="card-body">
        @foreach($payoutSummary['items'] as $orderId => $orderData)
            @php $order = $orderData['order']; @endphp
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <strong>{{ $order->reference }}</strong>
                        <span class="small text-muted ms-2">{{ $order->order_date?->format('M d, Y') }}</span>
                    </div>
                    <div class="fw-bold text-success">
                        Commission: ₦{{ number_format($orderData['commission'], 2) }}
                    </div>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Variant</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end">Line Total</th>
                                <th class="text-end">Commission (10%)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($orderData['items'] as $item)
                                <tr>
                                    <td>{{ $item->product?->name }}</td>
                                    <td class="text-muted small">{{ $item->variant?->options_label }}</td>
                                    <td class="text-center">{{ $item->quantity }}</td>
                                    <td class="text-end">₦{{ number_format($item->unit_price, 2) }}</td>
                                    <td class="text-end">₦{{ number_format($item->line_total, 2) }}</td>
                                    <td class="text-end text-success fw-bold">₦{{ number_format($item->commission, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
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

        // Filter button
        document.getElementById('payout-filter')?.addEventListener('click', function() {
            table.ajax.reload();
        });
    }
});
</script>
@endpush
