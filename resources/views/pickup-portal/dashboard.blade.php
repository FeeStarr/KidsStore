@extends('layouts.pickup-portal', ['title' => 'Dashboard — '.session('portal_station_name')])
@section('content')

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <i class="bi bi-box-seam me-2"></i>
        {{ session('portal_station_name') }}
    </h4>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#accountDetailsModal">
            <i class="bi bi-bank me-1"></i>Account Details
        </button>
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

@if(request('payment_confirmed'))
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle me-2"></i><strong>Payment Confirmed!</strong> You can now release the order to the customer.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if(request('payment_rejected'))
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-x-circle me-2"></i><strong>Payment Not Verified.</strong> Please ask the customer to retry or try a different payment method.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if($filter === 'picked_up')
    {{-- Commission Summary Cards --}}
    @if($commissionSummary)
    <div class="row mb-3">
        <div class="col-md-4">
            <div class="card border-start border-4 border-success">
                <div class="card-body text-center py-2">
                    <div class="small text-muted">Total Earned</div>
                    <div class="fs-4 fw-bold text-success">₦{{ number_format($commissionSummary['total_earned'], 2) }}</div>
                    <div class="small text-muted">{{ $counts['picked_up'] }} item(s) picked up</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-start border-4 border-primary">
                <div class="card-body text-center py-2">
                    <div class="small text-muted">Total Paid</div>
                    <div class="fs-4 fw-bold text-primary">₦{{ number_format($commissionSummary['total_paid'], 2) }}</div>
                    <div class="small text-muted">{{ $commissionSummary['paid_count'] }} item(s)</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-start border-4 border-warning">
                <div class="card-body text-center py-2">
                    <div class="small text-muted">Total Pending</div>
                    <div class="fs-4 fw-bold text-warning">₦{{ number_format($commissionSummary['total_pending'], 2) }}</div>
                    <div class="small text-muted">{{ $commissionSummary['pending_count'] }} item(s)</div>
                </div>
            </div>
        </div>
    </div>
    @endif

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
                        <th class="text-end">Commission ({{ \App\Models\Setting::get('commission_rate', 10) }}%)</th>
                        <th class="text-center">Status</th>
                        <th>Picked Up At</th>
                    </tr>
                </thead>
                <tbody></tbody>
                <tfoot>
                    <tr class="table-light fw-bold">
                        <td colspan="10" class="text-end text-muted small">Showing {{ $counts['picked_up'] }} picked up item(s) total</td>
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
                @php
                    $order = $items->first()->order;
                    $canReceive = in_array($order->status, ['shipping to station', 'out for delivery', 'ready for pick up']);
                @endphp
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <input type="checkbox" class="select-all me-2" {{ $filter === 'pending' && !$canReceive ? 'disabled' : '' }}>
                            <strong>{{ $order->reference }}</strong>
                            <span class="small text-muted ms-2">Ordered {{ $order->order_date?->format('M d, Y') }}</span>
                            @if($filter === 'pending')
                                <span class="badge bg-secondary ms-2">Order: {{ $order->status }}</span>
                                @if(!$canReceive)
                                    <span class="badge bg-warning text-dark ms-1">Awaiting shipment</span>
                                @endif
                            @endif
                        </div>
                    <div class="small d-flex gap-2 align-items-center">
                        <span>Customer: {{ $order->customer?->name ?? '—' }}</span>
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
                                    <th class="text-end">Commission ({{ \App\Models\Setting::get('commission_rate', 10) }}%)</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($items as $item)
                                    <tr @if($filter === 'pending' && !$canReceive) class="table-light" @endif>
                                        <td><input type="checkbox" name="item_ids[]" value="{{ $item->id }}" class="item-checkbox" {{ $filter === 'pending' && !$canReceive ? 'disabled' : '' }}></td>
                                        <td>{{ $item->product?->name }}</td>
                                        <td class="text-muted small">{{ $item->variant?->options_label }}</td>
                                        <td class="text-center">{{ $item->quantity }}</td>
                                        <td class="text-end">₦{{ number_format($item->line_total, 2) }}</td>
                                        <td class="text-end text-success">₦{{ number_format($item->line_total * 0.10, 2) }}</td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary">{{ $item->status_label }}</span>
                                            @if($filter === 'pending' && $canReceive)
                                                <form method="POST" action="{{ route('pickup-portal.items.received', $item) }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-primary ms-1 mark-received-btn"
                                                            data-item="{{ $item->product?->name }}">
                                                        <i class="bi bi-check-circle me-1"></i>Received
                                                    </button>
                                                </form>
                                            @elseif($filter === 'pending' && !$canReceive)
                                                <span class="text-muted small ms-1">Order must be shipped first</span>
                                            @endif
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
                $isVerificationPending = $order->payment_status === 'verification_pending';
                $isVerificationFailed = $order->payment_status === 'verification_failed';
                $isUnderReview = $order->payment_status === 'under_review';
                $balance = ($order->grand_total ?? 0) - ($order->amount_paid ?? 0);
                $pendingVerification = $order->latestPendingVerification();
                $stationAccount = $bankAccount;
            @endphp
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <strong>{{ $order->reference }}</strong>
                        <span class="small text-muted ms-2">Ordered {{ $order->order_date?->format('M d, Y') }}</span>
                        @if($isPaid)
                            <span class="badge bg-success ms-2"><i class="bi bi-check-circle me-1"></i>Paid</span>
                        @elseif($isVerificationPending)
                            <span class="badge bg-info text-dark ms-2" id="verify-badge-{{ $order->id }}">
                                <i class="bi bi-clock-history me-1"></i>Verification Pending
                            </span>
                        @elseif($isVerificationFailed)
                            <span class="badge bg-danger ms-2"><i class="bi bi-x-circle me-1"></i>Verification Failed</span>
                        @elseif($isUnderReview)
                            <span class="badge bg-warning text-dark ms-2"><i class="bi bi-hourglass-split me-1"></i>Under Review</span>
                        @else
                            <span class="badge bg-warning text-dark ms-2"><i class="bi bi-exclamation-circle me-1"></i>Unpaid — ₦{{ number_format($balance, 2) }} remaining</span>
                        @endif
                    </div>
                    <div class="small d-flex gap-2 align-items-center">
                        <span>Customer: {{ $order->customer?->name ?? '—' }}</span>
                        @if($order->payment_method)
                            <span class="badge bg-light text-dark">
                                <i class="bi bi-credit-card me-1"></i>{{ ucfirst(str_replace('_', ' ', $order->payment_method)) }}
                            </span>
                        @endif
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
                                <th>Product</th>
                                <th>Variant</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Price</th>
                                <th class="text-end">Commission ({{ \App\Models\Setting::get('commission_rate', 10) }}%)</th>
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
                                        @elseif($isUnderReview)
                                            <span class="text-warning small me-2">
                                                <i class="bi bi-hourglass-split me-1"></i>Payment under review
                                            </span>
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
                {{-- Footer: payment actions --}}
                @if(! $isPaid)
                <div class="card-footer bg-light">
                    {{-- Verification pending: show countdown --}}
                    @if($isVerificationPending && $pendingVerification)
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <span class="small text-muted"><i class="bi bi-clock-history me-1"></i>Payment submitted for verification</span>
                            <span class="badge bg-info text-dark" id="countdown-{{ $order->id }}"
                                  data-submitted-at="{{ $pendingVerification->submitted_at->toIso8601String() }}">
                                @php echo $pendingVerification->getCountdownDisplay(); @endphp
                            </span>
                            <span class="small text-muted">— waiting for admin confirmation</span>
                        </div>

                    {{-- Under review: show waiting message --}}
                    @elseif($isUnderReview)
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <span class="small text-warning"><i class="bi bi-hourglass-split me-1"></i>Payment under review — waiting for admin to confirm</span>
                        </div>

                    {{-- Verification failed: show retry option --}}
                    @elseif($isVerificationFailed)
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="small text-danger me-1"><i class="bi bi-exclamation-triangle me-1"></i>Payment not verified</span>
                            <form method="POST" action="{{ route('pickup-portal.submit-payment', $order) }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-warning">
                                    <i class="bi bi-arrow-repeat me-1"></i>Resubmit Payment
                                </button>
                            </form>
                        </div>

                    {{-- Unpaid: show bank details + submit button --}}
                    @else
                        <div class="d-flex gap-2 align-items-center flex-wrap">
                            <span class="small text-muted me-1">Collect payment:</span>

                            <button type="button" class="btn btn-sm btn-success"
                                    onclick="portalPayNow({{ $order->id }})">
                                <i class="bi bi-lightning me-1"></i>Pay Now (Paystack)
                            </button>

                            @if($stationAccount)
                                <button type="button" class="btn btn-sm btn-outline-primary"
                                        data-bs-toggle="modal" data-bs-target="#bankDetailsModal-{{ $order->id }}">
                                    <i class="bi bi-bank me-1"></i>Manual Transfer
                                </button>
                            @endif

                            <form method="POST" action="{{ route('pickup-portal.submit-payment', $order) }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-warning"
                                        onclick="return confirm('Submit payment for verification? Admin will review within 40 minutes.')">
                                    <i class="bi bi-send me-1"></i>Payment Submitted
                                </button>
                            </form>
                        </div>

                        {{-- Bank Details Modal --}}
                        @if($stationAccount)
                        <div class="modal fade" id="bankDetailsModal-{{ $order->id }}" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Manual Bank Transfer</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p class="small text-muted mb-3">If Paystack is unavailable, share these details with the customer for manual bank transfer. After transfer, click "Payment Submitted" to send for admin verification.</p>
                                        <table class="table table-sm mb-0">
                                            <tr><td class="text-muted" style="width:120px">Bank</td><td><strong>{{ $stationAccount->bank_name }}</strong></td></tr>
                                            <tr><td class="text-muted">Account Name</td><td>{{ $stationAccount->bank_account_name }}</td></tr>
                                            <tr>
                                                <td class="text-muted">Account Number</td>
                                                <td>
                                                    <span class="font-monospace fw-bold">{{ $stationAccount->bank_account_number }}</span>
                                                    <button class="btn btn-sm btn-outline-secondary ms-2" onclick="navigator.clipboard.writeText('{{ $stationAccount->bank_account_number }}')">
                                                        <i class="bi bi-clipboard me-1"></i>Copy
                                                    </button>
                                                </td>
                                            </tr>
                                            <tr><td class="text-muted">Amount</td><td class="fw-bold text-primary">₦{{ number_format($balance, 2) }}</td></tr>
                                        </table>
                                        @if($stationAccount->instructions)
                                            <div class="mt-2 small text-muted"><i class="bi bi-info-circle me-1"></i>{{ $stationAccount->instructions }}</div>
                                        @endif
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif
                    @endif
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
                { data: 'status', className: 'text-center', render: function(data, type, row) { return '<span class="badge ' + row.status_class + '">' + data + '</span>'; }, orderable: false },
                { data: 'picked_up_at' }
            ],
            order: [[9, 'desc']],
            pageLength: 25,
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

<script>
// Countdown timers for payment verification
function updateCountdowns() {
    document.querySelectorAll('[id^="countdown-"]').forEach(function(el) {
        const submittedAt = new Date(el.dataset.submittedAt);
        const now = new Date();
        const elapsed = Math.floor((now - submittedAt) / 1000);
        const remaining = Math.max(0, 2400 - elapsed);

        if (remaining <= 0) {
            el.textContent = 'Overdue — awaiting admin';
            el.classList.remove('bg-info');
            el.classList.add('bg-danger');
        } else {
            const mins = Math.floor(remaining / 60);
            const secs = remaining % 60;
            el.textContent = mins + 'm ' + secs + 's remaining';
        }
    });
}
updateCountdowns();
setInterval(updateCountdowns, 1000);

// AJAX polling — check payment status every 15s, reload on change
(function() {
    const pollers = {};
    const lastStatus = {};

    document.querySelectorAll('[id^="countdown-"]').forEach(function(el) {
        const orderId = el.id.replace('countdown-', '');
        lastStatus[orderId] = 'verification_pending';

        pollers[orderId] = setInterval(function() {
            fetch('{{ route("pickup-portal.payment-status", "__ID__") }}'.replace('__ID__', orderId), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.payment_status !== lastStatus[orderId]) {
                    clearInterval(pollers[orderId]);
                    var param = data.payment_status === 'paid' ? 'payment_confirmed=1' : 'payment_rejected=1';
                    var sep = window.location.search ? '&' : '?';
                    window.location.search = window.location.search + sep + param;
                }
            })
            .catch(function() {});
        }, 15000);
    });
})();
</script>

<script>
// Portal Pay Now — initiate Paystack virtual account for customer
function portalPayNow(orderId) {
    var modal = document.getElementById('portalPayNowModal');
    var loading = document.getElementById('ppn-loading');
    var account = document.getElementById('ppn-account');
    var errDiv = document.getElementById('ppn-error');
    var bankName = document.getElementById('ppn-bank-name');
    var acctNum = document.getElementById('ppn-account-number');
    var amountEl = document.getElementById('ppn-amount');
    var countdown = document.getElementById('ppn-countdown');
    var checkBtn = document.getElementById('ppn-check-btn');
    var statusEl = document.getElementById('ppn-status');

    // Reset
    loading.style.display = '';
    account.style.display = 'none';
    errDiv.style.display = 'none';
    if (checkBtn) { checkBtn.disabled = false; checkBtn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i>Check Payment'; }

    var bsModal = new bootstrap.Modal(modal);
    bsModal.show();

    var csrf = document.querySelector('meta[name="csrf-token"]').content;

    fetch('/pickup-portal/orders/' + orderId + '/initiate-payment', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            loading.style.display = 'none';
            account.style.display = '';
            bankName.textContent = data.virtual_bank_name || 'Paystack';
            acctNum.textContent = data.virtual_account_number || '0000000000';
            amountEl.textContent = '\u20A6' + Number(data.amount).toLocaleString(undefined, {minimumFractionDigits: 2});

            var seconds = data.seconds_remaining || 0;
            function tick() {
                if (seconds <= 0) { countdown.textContent = 'expired'; return; }
                var m = Math.floor(seconds / 60);
                var s = seconds % 60;
                countdown.textContent = m + ':' + String(s).padStart(2, '0');
                seconds--;
                setTimeout(tick, 1000);
            }
            tick();

            // Store for check
            modal.dataset.orderId = orderId;
            modal.dataset.seconds = seconds;

            // Auto-poll
            var pollInterval = setInterval(function() {
                if (seconds > 0) portalCheckPayment(orderId);
                else clearInterval(pollInterval);
            }, 30000);
        } else {
            loading.style.display = 'none';
            errDiv.style.display = '';
            errDiv.textContent = data.message || 'Could not generate payment account.';
        }
    })
    .catch(function() {
        loading.style.display = 'none';
        errDiv.style.display = '';
        errDiv.textContent = 'Network error. Please try again.';
    });

    if (checkBtn) {
        checkBtn.onclick = function() { portalCheckPayment(orderId); };
    }
}

function portalCheckPayment(orderId) {
    var checkBtn = document.getElementById('ppn-check-btn');
    var statusEl = document.getElementById('ppn-status');
    var csrf = document.querySelector('meta[name="csrf-token"]').content;

    if (checkBtn) { checkBtn.disabled = true; checkBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Checking...'; }
    if (statusEl) statusEl.textContent = '';

    fetch('/pickup-portal/orders/' + orderId + '/query-payment', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.paid || data.payment_status === 'paid') {
            var modal = document.getElementById('portalPayNowModal');
            bootstrap.Modal.getInstance(modal).hide();
            location.reload();
            return;
        }
        if (statusEl) {
            statusEl.textContent = data.throttled
                ? 'Please wait before checking again.'
                : 'Payment not yet received. Customer should transfer the exact amount.';
        }
    })
    .catch(function() {
        if (statusEl) statusEl.textContent = 'Network error.';
    })
    .finally(function() {
        if (checkBtn) { checkBtn.disabled = false; checkBtn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i>Check Payment'; }
    });
}
</script>
@endpush

{{-- Portal Pay Now Modal --}}
<div class="modal fade" id="portalPayNowModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-lightning me-2"></i>Paystack Bank Transfer</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <div id="ppn-loading">
                    <div class="spinner-border text-primary mb-2" role="status"></div>
                    <div class="small text-muted">Generating virtual account...</div>
                </div>
                <div id="ppn-account" style="display:none">
                    <p class="small text-muted mb-3">Share these details with the customer. Transfer must be the exact amount.</p>
                    <div class="bg-light rounded p-3 mb-3">
                        <div class="small text-muted mb-1">Bank</div>
                        <div class="fw-bold fs-5" id="ppn-bank-name"></div>
                    </div>
                    <div class="bg-light rounded p-3 mb-3">
                        <div class="small text-muted mb-1">Account Number</div>
                        <div class="fw-bold fs-3 font-monospace" id="ppn-account-number"></div>
                        <button type="button" class="btn btn-sm btn-outline-secondary mt-1"
                                onclick="navigator.clipboard.writeText(document.getElementById('ppn-account-number').textContent);this.textContent='Copied!'">Copy</button>
                    </div>
                    <div class="mb-2">
                        <span class="fw-bold fs-5 text-success" id="ppn-amount"></span>
                    </div>
                    <div class="small text-muted mb-3">
                        Expires in <strong id="ppn-countdown">...</strong>
                    </div>
                    <button id="ppn-check-btn" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-arrow-clockwise me-1"></i>Check Payment
                    </button>
                    <div id="ppn-status" class="small text-muted mt-2"></div>
                </div>
                <div id="ppn-error" style="display:none" class="text-danger small"></div>
            </div>
        </div>
    </div>
</div>

{{-- Account Details Modal --}}
@php
    $portalAccounts = \App\Models\BankAccount::where('is_active', true)->orderByDesc('is_default')->get();
@endphp
<div class="modal fade" id="accountDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-bank me-2"></i>Bank Account Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                @if($portalAccounts->isEmpty())
                    <p class="text-muted text-center py-3">No bank accounts configured. Please contact admin.</p>
                @else
                    <p class="small text-muted mb-3">Share these details with the customer for payment.</p>
                    @foreach($portalAccounts as $ba)
                        <div class="card {{ $ba->is_default ? 'border-primary' : 'border-light' }} mb-2">
                            <div class="card-body py-2 px-3">
                                @if($ba->bank_name)
                                    <div class="small text-muted">Bank</div>
                                    <div class="fw-semibold">{{ $ba->bank_name }}</div>
                                @endif
                                @if($ba->bank_account_name)
                                    <div class="small text-muted mt-1">Account Name</div>
                                    <div>{{ $ba->bank_account_name }}</div>
                                @endif
                                @if($ba->bank_account_number)
                                    <div class="small text-muted mt-1">Account Number</div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="font-monospace fw-bold fs-5">{{ $ba->bank_account_number }}</span>
                                        <button class="btn btn-sm btn-outline-secondary" onclick="navigator.clipboard.writeText('{{ $ba->bank_account_number }}')">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </div>
                                @endif
                                @if($ba->is_default)
                                    <span class="badge bg-primary mt-1">Default</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

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
