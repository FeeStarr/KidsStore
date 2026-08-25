@extends('layouts.admin', ['title' => 'Payouts - '. $pickupStation->name ])
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Payouts - {{ $pickupStation->name }}</h3>
    <div>
        <a href="{{ route('admin.pickup-payouts.index') }}" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>
</div>

{{-- Payout Summary --}}
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

{{-- Commission Breakdown - DataTable --}}
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-list-check me-2"></i>Picked Up Items - Commission Breakdown</h5>
        <div class="d-flex gap-2 align-items-center">
            <select id="paid-status-filter" class="form-select form-select-sm" style="width:130px">
                <option value="">All Status</option>
                <option value="unpaid">Unpaid</option>
                <option value="paid">Paid</option>
            </select>
            <input type="date" id="item-from" class="form-control form-control-sm" style="width:150px">
            <input type="date" id="item-to" class="form-control form-control-sm" style="width:150px">
            <button id="item-filter" class="btn btn-sm btn-primary">Filter</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.querySelectorAll('.item-paid-check:not(:checked)').forEach(cb => cb.checked = true); updateSelectAllSection()">Select All Unpaid</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.querySelectorAll('.item-paid-check:checked').forEach(cb => cb.checked = false); updateSelectAllSection()">Deselect All</button>
        </div>
    </div>
    <div class="card-body">
        <form method="post" action="{{ route('admin.pickup-payouts.mark-paid', $pickupStation) }}" id="markPaidForm">
            @csrf

            <table id="payout-items-table" class="table table-sm" style="width:100%">
                <thead>
                    <tr>
                        <th style="width:30px"></th>
                        <th>Order</th>
                        <th>Date</th>
                        <th>Product</th>
                        <th>Variant</th>
                        <th class="text-center">Qty</th>
                        <th class="text-end">Unit Price</th>
                        <th class="text-end">Line Total</th>
                        <th class="text-end">Commission (10%)</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody></tbody>
                <tfoot>
                    <tr class="table-light fw-bold">
                        <td colspan="8" class="text-end">Total Commission (page):</td>
                        <td class="text-end text-success" id="page-commission-total">-</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>

            <div class="card mb-3 mt-3" id="noteCard" style="display:none">
                <div class="card-body py-2">
                    <label class="form-label form-label-sm mb-1">Note (optional)</label>
                    <textarea name="note" class="form-control form-control-sm" rows="2" placeholder="e.g., Bank transfer reference, payout date"></textarea>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-3" id="markPaidSection" style="display:none">
                <div class="small text-muted" id="selectedInfo">0 item(s) selected</div>
                <button type="submit" class="btn btn-primary" id="markPaidBtn">
                    <i class="bi bi-check-circle me-1"></i>Mark Selected as Paid
                </button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('styles')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
@endpush

@push('scripts')
<script>
function updateSelectAllSection() {
    const checked = document.querySelectorAll('.item-paid-check:checked');
    const section = document.getElementById('markPaidSection');
    const noteCard = document.getElementById('noteCard');
    const info = document.getElementById('selectedInfo');
    if (checked.length > 0) {
        section.style.display = 'flex';
        noteCard.style.display = 'block';
        info.textContent = checked.length + ' item(s) selected - ₦' + Array.from(checked).reduce((sum, cb) => {
            return sum + parseFloat(cb.closest('tr').querySelector('.commission-val')?.textContent.replace(/[₦,]/g, '') || 0);
        }, 0).toFixed(2);
    } else {
        section.style.display = 'none';
        noteCard.style.display = 'none';
    }
}
document.querySelectorAll('.item-paid-check').forEach(cb => {
    cb.addEventListener('change', updateSelectAllSection);
});
document.getElementById('markPaidForm')?.addEventListener('submit', function(e) {
    const checked = document.querySelectorAll('.item-paid-check:checked');
    if (checked.length === 0) { e.preventDefault(); return; }
    if (!confirm('Mark ' + checked.length + ' item(s) as paid?')) { e.preventDefault(); }
});

// DataTable
$(function () {
    var table = $('#payout-items-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("admin.pickup-payouts.show-data", $pickupStation) }}',
            data: function(d) {
                d.paid_status = document.getElementById('paid-status-filter')?.value || '';
                d.from = document.getElementById('item-from')?.value || '';
                d.to = document.getElementById('item-to')?.value || '';
            }
        },
        columns: [
            { data: 'checkbox', orderable: false, searchable: false, className: 'text-center' },
            { data: 'order_reference', name: 'order' },
            { data: 'order_date', name: 'order_date' },
            { data: 'product', name: 'product' },
            { data: 'variant', name: 'variant' },
            { data: 'quantity', className: 'text-center', orderable: false },
            { data: 'unit_price', className: 'text-end', orderable: false },
            { data: 'line_total', className: 'text-end', orderable: false },
            { data: 'commission', className: 'text-end text-success fw-bold commission-val', orderable: false },
            { data: 'status', className: 'text-center', orderable: false, render: function(data, type, row) {
                return '<span class="badge ' + row.status_class + '">' + data + '</span>';
            }}
        ],
        order: [[2, 'desc']],
        pageLength: 25,
        drawCallback: function() {
            var api = this.api();
            var total = 0;
            api.column(8, { page: 'current' }).data().each(function(val) {
                total += parseFloat(val.replace(/[₦,]/g, '')) || 0;
            });
            $('#page-commission-total').text('₦' + total.toLocaleString(undefined, {minimumFractionDigits: 2}));
            // Rebind checkboxes after redraw
            document.querySelectorAll('.item-paid-check').forEach(cb => {
                cb.removeEventListener('change', updateSelectAllSection);
                cb.addEventListener('change', updateSelectAllSection);
            });
        }
    });

    $('#item-filter').on('click', function() { table.ajax.reload(); });
});
</script>
@endpush
