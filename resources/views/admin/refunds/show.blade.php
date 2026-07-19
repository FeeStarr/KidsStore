@extends('layouts.admin', ['title' => 'Return #'.$refundRequest->id])
@section('content')

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Return #{{ $refundRequest->id }}</h3>
    <a href="{{ route('admin.refunds.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> All Returns
    </a>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

@php
    $badge = match($refundRequest->status) {
        'requested'            => 'bg-info text-dark',
        'pending_review'       => 'bg-warning text-dark',
        'awaiting_evidence'    => 'bg-warning text-dark',
        'approved'             => 'bg-primary',
        'rejected'             => 'bg-danger',
        'awaiting_shipment'    => 'bg-info text-dark',
        'in_transit'           => 'bg-info',
        'received'             => 'bg-secondary',
        'inspection'           => 'bg-secondary',
        'refund_approved'      => 'bg-primary',
        'refund_processing'    => 'bg-warning text-dark',
        'refunded'             => 'bg-success',
        'refund_failed'        => 'bg-dark',
        'replacement_approved' => 'bg-primary',
        'replacement_shipped'  => 'bg-info',
        'replacement_delivered'=> 'bg-success',
        'completed'            => 'bg-success',
        'cancelled'            => 'bg-dark',
        default                => 'bg-secondary',
    };
@endphp

<div class="row g-3 mb-3">
    {{-- Left: request details --}}
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <h5 class="mb-3">Request Details</h5>
            <dl class="row mb-0">
                <dt class="col-5">Status</dt>
                <dd class="col-7"><span class="badge {{ $badge }}">{{ ucfirst(str_replace('_', ' ', $refundRequest->status)) }}</span></dd>

                <dt class="col-5">Scope</dt>
                <dd class="col-7">{{ $refundRequest->getScopeLabel() }}</dd>

                <dt class="col-5">Refund Amount</dt>
                <dd class="col-7 fw-bold text-primary">₦{{ number_format($refundRequest->amount, 2) }}</dd>

                <dt class="col-5">Reason</dt>
                <dd class="col-7">{{ $refundRequest->reason_label }}</dd>

                <dt class="col-5">Details</dt>
                <dd class="col-7">{{ $refundRequest->details ?: '—' }}</dd>

                <dt class="col-5">Submitted</dt>
                <dd class="col-7">{{ $refundRequest->created_at->format('M d, Y h:ia') }}</dd>

                @if($refundRequest->reviewed_at)
                    <dt class="col-5">Reviewed by</dt>
                    <dd class="col-7">{{ $refundRequest->reviewer?->name }} · {{ $refundRequest->reviewed_at->format('M d, Y h:ia') }}</dd>
                @endif

                @if($refundRequest->admin_note)
                    <dt class="col-5">Admin Note</dt>
                    <dd class="col-7">{{ $refundRequest->admin_note }}</dd>
                @endif

                @if($refundRequest->inspected_at)
                    <dt class="col-5">Inspected by</dt>
                    <dd class="col-7">{{ $refundRequest->inspector?->name }} · {{ $refundRequest->inspected_at->format('M d, Y h:ia') }}</dd>
                @endif

                @if($refundRequest->inspection_notes)
                    <dt class="col-5">Inspection Notes</dt>
                    <dd class="col-7">{{ $refundRequest->inspection_notes }}</dd>
                @endif

                @if($refundRequest->opay_refund_no)
                    <dt class="col-5">OPay Refund No.</dt>
                    <dd class="col-7 font-monospace small">{{ $refundRequest->opay_refund_no }}</dd>
                @endif
            </dl>

            @if($refundRequest->evidence_path)
                <div class="mt-3">
                    <div class="small text-muted mb-1">Evidence photo:</div>
                    <a href="{{ $refundRequest->evidence_url }}" target="_blank">
                        <img src="{{ $refundRequest->evidence_url }}"
                             style="max-height:200px;max-width:100%;border-radius:.5rem;object-fit:cover"
                             alt="Evidence">
                    </a>
                </div>
            @endif

            @if($refundRequest->evidence_video_path)
                <div class="mt-3">
                    <div class="small text-muted mb-1">Evidence video:</div>
                    <a href="{{ $refundRequest->evidence_video_url }}" target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-play-circle me-1"></i>View Video
                    </a>
                </div>
            @endif
        </div></div>
    </div>

    {{-- Right: order summary --}}
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <h5 class="mb-3">Order Summary</h5>
            <dl class="row mb-2">
                <dt class="col-5">Order Ref</dt>
                <dd class="col-7">
                    <a href="{{ route('admin.orders.show', $refundRequest->order) }}">
                        {{ $refundRequest->order->reference }}
                    </a>
                </dd>
                <dt class="col-5">Customer</dt>
                <dd class="col-7">{{ $refundRequest->order->customer?->name }}</dd>
                <dt class="col-5">Order Total</dt>
                <dd class="col-7">₦{{ number_format($refundRequest->order->grand_total, 2) }}</dd>
                <dt class="col-5">Amount Paid</dt>
                <dd class="col-7">₦{{ number_format($refundRequest->order->amount_paid, 2) }}</dd>
                <dt class="col-5">Payment</dt>
                <dd class="col-7">{{ ucfirst($refundRequest->order->payment_status) }}</dd>
            </dl>

            <table class="table table-sm table-bordered mb-0 mt-2">
                <thead class="table-light">
                    <tr><th>Item</th><th>Qty</th><th class="text-end">Line Total</th></tr>
                </thead>
                <tbody>
                @foreach($refundRequest->order->items as $it)
                    <tr class="{{ $refundRequest->orderItem && $refundRequest->order_item_id == $it->id ? 'table-warning fw-semibold' : '' }}">
                        <td class="small">
                            {{ $it->product?->name }}
                            @if($it->variant?->options_label) <span class="text-muted">— {{ $it->variant->options_label }}</span>@endif
                            @if($refundRequest->order_item_id == $it->id)
                                <span class="badge bg-warning text-dark ms-1">Return item</span>
                            @endif
                            @if($it->product && !$it->product->is_returnable)
                                <span class="badge bg-secondary ms-1" style="font-size:10px;">Non-returnable</span>
                            @endif
                        </td>
                        <td>{{ $it->quantity }}</td>
                        <td class="text-end">₦{{ number_format($it->line_total, 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div></div>
    </div>
</div>

{{-- ── Action Panel ───────────────────────────────────────────────────────── --}}
@php $s = $refundRequest->status; @endphp

@if(in_array($s, ['requested', 'pending_review']))
    <div class="row g-3">
        <div class="col-md-4">
            <div class="card border-warning border-2"><div class="card-body">
                <h6 class="text-warning mb-3"><i class="bi bi-camera me-1"></i>Request Evidence</h6>
                <form method="post" action="{{ route('admin.refunds.request-evidence', $refundRequest) }}">
                    @csrf
                    <div class="mb-3">
                        <textarea name="admin_note" rows="2" class="form-control form-control-sm"
                                  placeholder="e.g. Please upload clear photos of the damaged item"></textarea>
                    </div>
                    <button class="btn btn-warning"><i class="bi bi-camera me-1"></i>Request Evidence</button>
                </form>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card border-success border-2"><div class="card-body">
                <h6 class="text-success mb-3"><i class="bi bi-check-circle me-1"></i>Approve</h6>
                <form method="post" action="{{ route('admin.refunds.approve', $refundRequest) }}">
                    @csrf
                    <div class="mb-3">
                        <textarea name="admin_note" rows="2" class="form-control form-control-sm"
                                  placeholder="Note to customer (optional)"></textarea>
                    </div>
                    <button class="btn btn-success" onclick="return confirm('Approve this return?')">
                        <i class="bi bi-check2-circle me-1"></i>Approve Return
                    </button>
                </form>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card border-danger border-2"><div class="card-body">
                <h6 class="text-danger mb-3"><i class="bi bi-x-circle me-1"></i>Reject</h6>
                <form method="post" action="{{ route('admin.refunds.reject', $refundRequest) }}">
                    @csrf
                    <div class="mb-3">
                        <textarea name="admin_note" rows="2" class="form-control form-control-sm" required
                                  placeholder="Reason for rejection *"></textarea>
                    </div>
                    <button class="btn btn-danger"><i class="bi bi-x-circle me-1"></i>Reject Request</button>
                </form>
            </div></div>
        </div>
    </div>

@elseif($s === 'awaiting_evidence')
    <div class="alert alert-warning">
        <i class="bi bi-hourglass-split me-1"></i>
        Waiting for customer to upload additional evidence.
        @if($refundRequest->admin_note)
            <div class="small mt-1">Message: {{ $refundRequest->admin_note }}</div>
        @endif
    </div>

@elseif(in_array($s, ['approved', 'awaiting_shipment', 'in_transit']))
    <div class="card border-info border-2"><div class="card-body">
        <h6 class="text-info mb-3"><i class="bi bi-box me-1"></i>Item Shipment</h6>
        <p class="small text-muted mb-3">The return has been approved. Mark the item as received when it arrives.</p>
        <form method="post" action="{{ route('admin.refunds.mark-received', $refundRequest) }}">
            @csrf
            <div class="mb-3">
                <textarea name="admin_note" rows="2" class="form-control form-control-sm"
                          placeholder="Note about received item (optional)"></textarea>
            </div>
            <button class="btn btn-info text-white" onclick="return confirm('Mark item as received? Stock will be restored.')">
                <i class="bi bi-box-arrow-in-down me-1"></i>Mark Item Received
            </button>
        </form>
    </div></div>

@elseif($s === 'received')
    <div class="row g-3">
        <div class="col-md-6">
            <div class="card border-primary border-2"><div class="card-body">
                <h6 class="text-primary mb-3"><i class="bi bi-search me-1"></i>Inspection</h6>
                <p class="small text-muted mb-3">Inspect the returned item and choose an outcome.</p>
                <form method="post" action="{{ route('admin.refunds.inspect', $refundRequest) }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label form-label-sm">Inspection Outcome *</label>
                        <select name="outcome" class="form-select form-select-sm" required>
                            <option value="">— Select —</option>
                            <option value="refund">Approve Refund</option>
                            <option value="replacement">Approve Replacement</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <textarea name="notes" rows="2" class="form-control form-control-sm"
                                  placeholder="Inspection notes (optional)"></textarea>
                    </div>
                    <button class="btn btn-primary" onclick="return confirm('Submit inspection result?')">
                        <i class="bi bi-check2-circle me-1"></i>Submit Inspection
                    </button>
                </form>
            </div></div>
        </div>
    </div>

@elseif($s === 'refund_approved')
    <div class="card border-success border-2"><div class="card-body">
        <h6 class="text-success mb-3"><i class="bi bi-cash me-1"></i>Process Refund</h6>
        <p class="small text-muted mb-3">Inspection passed. Process the refund via OPay.</p>
        <form method="post" action="{{ route('admin.refunds.process-refund', $refundRequest) }}">
            @csrf
            <div class="mb-3">
                <textarea name="admin_note" rows="2" class="form-control form-control-sm"
                          placeholder="Note (optional)"></textarea>
            </div>
            <button class="btn btn-success" onclick="return confirm('Process refund of ₦{{ number_format($refundRequest->amount, 2) }}?')">
                <i class="bi bi-cash-stack me-1"></i>Process Refund — ₦{{ number_format($refundRequest->amount, 2) }}
            </button>
        </form>
    </div></div>

@elseif($s === 'replacement_approved')
    <div class="alert alert-primary">
        <i class="bi bi-arrow-repeat me-1"></i>
        Replacement approved. Ship the replacement item to the customer.
    </div>

@elseif(in_array($s, ['refunded', 'refund_failed', 'replacement_delivered', 'completed', 'cancelled', 'rejected']))
    <div class="alert alert-secondary">
        <i class="bi bi-check-circle me-1"></i>
        This return request is <strong>{{ ucfirst(str_replace('_', ' ', $refundRequest->status)) }}</strong>.
    </div>
@endif

{{-- ── Audit Trail ────────────────────────────────────────────────────────── --}}
@if($refundRequest->auditLogs->isNotEmpty())
<div class="card border-0 shadow-sm mt-3">
    <div class="card-header"><i class="bi bi-clock-history me-1"></i> Audit Trail</div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <tbody>
            @foreach($refundRequest->auditLogs as $log)
                <tr>
                    <td class="text-nowrap">{{ $log->created_at->format('M d, h:ia') }}</td>
                    <td><span class="badge bg-light text-dark">{{ ucfirst(str_replace('_', ' ', $log->action)) }}</span></td>
                    <td class="small text-muted">{{ $log->user?->name ?: 'System' }}</td>
                    <td class="small">{{ $log->details }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
