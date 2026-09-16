@extends('layouts.delivery-portal', ['title' => 'Order ' . $order->reference])
@section('content')
@php
    $statusBadge = match($order->delivery_status) {
        'assigned' => 'primary',
        'received' => 'warning text-dark',
        'delivered' => 'success',
        'failed' => 'danger',
        default => 'secondary',
    };
@endphp
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">{{ $order->reference }}</h5>
    <span class="badge bg-{{ $statusBadge }} fs-6">{{ $order->getDeliveryStatusLabel() }}</span>
</div>

<div class="card mb-3">
    <div class="card-body">
        <h6 class="card-title text-muted mb-3">Customer</h6>
        <dl class="row mb-0">
            <dt class="col-4">Name</dt>
            <dd class="col-8">{{ $order->customer?->name ?? $order->guest_name ?? '-' }}</dd>
            <dt class="col-4">Phone</dt>
            <dd class="col-8">{{ $order->customer?->phone ?? $order->guest_phone ?? '-' }}</dd>
            <dt class="col-4">Location</dt>
            <dd class="col-8">{{ $order->deliveryLocation?->name ?? '-' }}</dd>
            <dt class="col-4">Address</dt>
            <dd class="col-8">{{ $order->delivery_address ?: '-' }}</dd>
        </dl>
    </div>
</div>

@php
    $paymentBadge = match($order->payment_status ?? 'unpaid') {
        'paid' => 'success',
        'unpaid' => 'danger',
        'partial' => 'warning text-dark',
        default => 'secondary',
    };
@endphp
<div class="card mb-3">
    <div class="card-body">
        <h6 class="card-title text-muted mb-3">Order Info</h6>
        <dl class="row mb-0">
            <dt class="col-4">Payment</dt>
            <dd class="col-8">
                <span class="badge bg-{{ $paymentBadge }}">{{ strtoupper($order->payment_status ?? 'unpaid') }}</span>
            </dd>
            <dt class="col-4">Delivery Fee</dt>
            <dd class="col-8">&#8358;{{ number_format($order->delivery_charge_amount ?? 0, 2) }}</dd>
            <dt class="col-4">Items</dt>
            <dd class="col-8">{{ $order->items->count() }} item(s)</dd>
        </dl>
    </div>
</div>

@if($order->items->isNotEmpty())
<div class="card mb-3">
    <div class="card-header">Items</div>
    <table class="table table-sm mb-0">
        <thead><tr><th>Product</th><th>Qty</th></tr></thead>
        <tbody>
            @foreach($order->items as $item)
                <tr>
                    <td>
                        {{ $item->product?->name ?? '-' }}
                        @if($item->variant && $item->variant->options_label)
                            <small class="text-muted d-block">{{ $item->variant->options_label }}</small>
                        @endif
                    </td>
                    <td>{{ $item->quantity }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

@if($order->delivery_status === 'failed')
<div class="card mb-3 border-danger">
    <div class="card-header bg-danger bg-opacity-10 text-danger">
        <i class="bi bi-exclamation-triangle me-1"></i>Delivery Issue
    </div>
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-4">Reason</dt>
            <dd class="col-8">{{ str_replace('_', ' ', ucfirst($order->delivery_issue_reason)) }}</dd>
            @if($order->delivery_issue_notes)
                <dt class="col-4">Notes</dt>
                <dd class="col-8">{{ $order->delivery_issue_notes }}</dd>
            @endif
        </dl>
    </div>
</div>
@endif

@if(in_array($order->delivery_status, ['assigned', 'received']))
<div class="d-grid gap-2">
    @if($order->delivery_status === 'assigned')
        <form id="received-form" action="{{ route('delivery-portal.deliveries.received', $order) }}" method="post">
            @csrf
        </form>
        <button type="button" class="btn btn-warning btn-action" onclick="confirmAction('received-form', 'Receive parcel?', 'Confirm you have physically received this parcel for delivery.')">
            <i class="bi bi-box-arrow-in-down me-2"></i>Mark as Received
        </button>
    @endif

    @if($order->delivery_status === 'received')
        <form id="delivered-form" action="{{ route('delivery-portal.deliveries.delivered', $order) }}" method="post">
            @csrf
        </form>
        <button type="button" class="btn btn-success btn-action" onclick="confirmAction('delivered-form', 'Deliver order?', 'Confirm this order has been delivered to the customer.')">
            <i class="bi bi-check-circle me-2"></i>Mark as Delivered
        </button>
    @endif

    <button type="button" class="btn btn-outline-danger btn-action" data-bs-toggle="modal" data-bs-target="#issueModal">
        <i class="bi bi-exclamation-triangle me-2"></i>Report Issue
    </button>
</div>

<!-- Issue Modal -->
<div class="modal fade" id="issueModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('delivery-portal.deliveries.issue', $order) }}" method="post">
                @csrf
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Report Delivery Issue</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Reason *</label>
                        <select name="reason" class="form-select" required>
                            <option value="">Select a reason...</option>
                            <option value="customer_unavailable">Customer unavailable</option>
                            <option value="customer_unreachable">Customer unreachable</option>
                            <option value="wrong_address">Wrong address</option>
                            <option value="customer_refused">Customer refused delivery</option>
                            <option value="damaged_parcel">Damaged parcel</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes (optional)</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Additional details..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Report Issue</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
function confirmAction(formId, title, text) {
    Swal.fire({
        title: title,
        text: text,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        confirmButtonText: 'Yes, confirm'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById(formId).submit();
        }
    });
}
</script>
@endpush
