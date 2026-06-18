@extends('layouts.admin', ['title' => 'Refund Request #'.$refundRequest->id])
@section('content')

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Refund Request #{{ $refundRequest->id }}</h3>
    <a href="{{ route('admin.refunds.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> All Requests
    </a>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

@php
    $badge = match($refundRequest->status) {
        'pending'  => 'bg-warning text-dark',
        'approved' => 'bg-primary',
        'refunded' => 'bg-success',
        'rejected' => 'bg-danger',
        'failed'   => 'bg-dark',
        default    => 'bg-secondary',
    };
@endphp

<div class="row g-3 mb-3">
    {{-- Left: request details --}}
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <h5 class="mb-3">Request Details</h5>
            <dl class="row mb-0">
                <dt class="col-5">Status</dt>
                <dd class="col-7"><span class="badge {{ $badge }}">{{ ucfirst($refundRequest->status) }}</span></dd>

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
                                <span class="badge bg-warning text-dark ms-1">Refund item</span>
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

{{-- Action panel --}}
@if($refundRequest->isPending())
    <div class="row g-3">
        <div class="col-md-6">
            <div class="card border-success border-2"><div class="card-body">
                <h6 class="text-success mb-3"><i class="bi bi-check-circle me-1"></i>Approve Refund</h6>
                <form method="post" action="{{ route('admin.refunds.approve', $refundRequest) }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label form-label-sm">Note to customer (optional)</label>
                        <textarea name="admin_note" rows="2" class="form-control form-control-sm"
                                  placeholder="e.g. Refund approved — will be processed in 3–5 working days"></textarea>
                    </div>
                    <button class="btn btn-success"
                            onclick="return confirm('Approve this refund of ₦{{ number_format($refundRequest->amount, 2) }}?')">
                        <i class="bi bi-check2-circle me-1"></i>Approve &amp; Process Refund
                    </button>
                </form>
            </div></div>
        </div>
        <div class="col-md-6">
            <div class="card border-danger border-2"><div class="card-body">
                <h6 class="text-danger mb-3"><i class="bi bi-x-circle me-1"></i>Reject Request</h6>
                <form method="post" action="{{ route('admin.refunds.reject', $refundRequest) }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label form-label-sm">Reason for rejection *</label>
                        <textarea name="admin_note" rows="2" class="form-control form-control-sm" required
                                  placeholder="e.g. Outside the 7-day refund window"></textarea>
                    </div>
                    <button class="btn btn-danger">
                        <i class="bi bi-x-circle me-1"></i>Reject Request
                    </button>
                </form>
            </div></div>
        </div>
    </div>
@endif
@endsection
