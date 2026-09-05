@extends('layouts.admin', ['title' => 'Order '.$order->reference])
@section('content')
    <div class="d-flex justify-content-between mb-3">
    <h3>Order {{ $order->reference }}</h3>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        @php
            $s        = $order->status;
            $isPickup = $order->isForPickup();
            $allStatuses = $order->getAvailableStatuses();
        @endphp

        {{-- Status dropdown - jump to any status --}}
        @if(! in_array($s, ['delivered', 'cancelled']))
            <form action="{{ route('admin.orders.update-status', $order) }}" method="post" class="d-flex gap-1" id="statusJumpForm">
                @csrf
                <select name="status" class="form-select form-select-sm" style="width:auto" id="statusJumpSelect">
                    <option value="" disabled selected>Jump to status...</option>
                    @foreach($allStatuses as $st)
                        @if($st !== $s)
                            <option value="{{ $st }}">{{ ucfirst($st) }}</option>
                        @endif
                    @endforeach
                </select>
            </form>
        @endif

        {{-- Quick action buttons (next logical step) --}}
        @if($s === 'confirmed')
            <form action="{{ route('admin.orders.processing', $order) }}" method="post">
                @csrf
                <button class="btn btn-secondary">
                    <i class="bi bi-gear me-1"></i>Processing
                </button>
            </form>
        @endif

        @if($s === 'processing' && ! $isPickup)
            <form action="{{ route('admin.orders.ship', $order) }}" method="post">
                @csrf
                <button class="btn btn-info text-white">
                    <i class="bi bi-truck me-1"></i>Out for Delivery
                </button>
            </form>
        @endif

        @if($s === 'processing' && $isPickup)
            <form action="{{ route('admin.orders.shipping-to-station', $order) }}" method="post">
                @csrf
                <button class="btn btn-info">
                    <i class="bi bi-truck me-1"></i>Ship to Station
                </button>
            </form>
        @endif

        @if($s === 'shipping to station' && $isPickup)
            <form action="{{ route('admin.orders.ready-for-pickup', $order) }}" method="post">
                @csrf
                <button class="btn btn-warning">
                    <i class="bi bi-geo-alt me-1"></i>Ready for Pick Up
                </button>
            </form>
        @endif

        @if(in_array($s, ['out for delivery', 'ready for pick up']))
            <form action="{{ route('admin.orders.deliver', $order) }}" method="post">
                @csrf
                <button class="btn btn-primary">
                    <i class="bi bi-bag-check me-1"></i>Delivered
                </button>
            </form>
        @endif

        {{-- cancel (any non-terminal state) --}}
        @if(! in_array($s, ['delivered', 'cancelled']))
            <form action="{{ route('admin.orders.cancel', $order) }}" method="post"
                  data-confirm="This will cancel the order{{ in_array($s, ['processing','shipping to station','out for delivery','ready for pick up']) ? ' and restore inventory' : '' }}."
                  data-confirm-title="Cancel Order?" data-confirm-yes="Yes, cancel">
                @csrf
                <button class="btn btn-outline-danger">
                    <i class="bi bi-x-circle me-1"></i>Cancel
                </button>
            </form>
        @endif
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6"><div class="card"><div class="card-body">
        <dl class="row mb-0">
            <dt class="col-4">Customer</dt><dd class="col-8">{{ $order->customer?->name ?? $order->guest_name ?? '-' }}</dd>
            <dt class="col-4">Date</dt><dd class="col-8">{{ $order->order_date->format('Y-m-d H:i') }}</dd>
            <dt class="col-4">Status</dt>
            <dd class="col-8"><span class="badge {{ match($order->status) {
                'delivered' => 'text-bg-success',
                'cancelled' => 'text-bg-danger',
                'pickup window expired' => 'text-bg-danger',
                'ready for pick up' => 'text-bg-warning text-dark',
                'confirmed' => 'text-bg-primary',
                default => 'text-bg-secondary'
            } }}">{{ $order->getStatusLabel() }}</span></dd>
            <dt class="col-4">Delivery</dt>
            <dd class="col-8">
                {{ $order->getDeliveryMethodLabel() }}
                @if($order->isForPickup() && $order->pickupStation)
                    <small class="text-muted d-block">{{ $order->pickupStation->name }} - {{ $order->pickupStation->full_address }}</small>
                @elseif($order->isForDelivery() && $order->delivery_address)
                    <small class="text-muted d-block">{{ $order->delivery_address }}</small>
                @endif
                @if($order->courier_name)
                    <small class="d-block mt-1"><i class="bi bi-truck me-1 text-primary"></i><strong>{{ $order->courier_name }}</strong></small>
                @endif
                @if($order->tracking_number)
                    <small class="d-block">
                        Tracking: <strong>{{ $order->tracking_number }}</strong>
                        @if($order->tracking_url)
                            <a href="{{ $order->tracking_url }}" target="_blank" class="ms-1">
                                <i class="bi bi-box-arrow-up-right"></i> Track
                            </a>
                        @endif
                    </small>
                @endif
            </dd>
            @if($order->isForPickup() && $order->pickupStation)
                @php $globalAccounts = \App\Models\BankAccount::active()->orderByDesc('is_default')->get(); @endphp
                @if($globalAccounts->isNotEmpty())
                    <dt class="col-4">Pickup Payment</dt>
                    <dd class="col-8">
                        @foreach($globalAccounts as $ba)
                            <div class="mb-2">
                                @if($ba->bank_name)<div><strong>Bank:</strong> {{ $ba->bank_name }}</div>@endif
                                @if($ba->bank_account_name)<div><strong>Account name:</strong> {{ $ba->bank_account_name }}</div>@endif
                                @if($ba->bank_account_number)
                                    <div>
                                        <strong>Account no:</strong>
                                        <span class="font-monospace">{{ $ba->bank_account_number }}</span>
                                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="navigator.clipboard.writeText('{{ $ba->bank_account_number }}')">Copy</button>
                                        @if($ba->is_default)<span class="badge bg-primary ms-2">Default</span>@endif
                                    </div>
                                @endif
                                @if($ba->instructions)<div class="text-muted small">{{ $ba->instructions }}</div>@endif
                            </div>
                        @endforeach
                    </dd>
                @else
                    @php $stationAccounts = $order->pickupStation->bankAccounts ?? collect(); @endphp
                    @if($stationAccounts->isNotEmpty())
                        <dt class="col-4">Pickup Payment</dt>
                        <dd class="col-8">
                            @foreach($stationAccounts as $ba)
                                <div class="mb-2">
                                    @if($ba->bank_name)<div><strong>Bank:</strong> {{ $ba->bank_name }}</div>@endif
                                    @if($ba->bank_account_name)<div><strong>Account name:</strong> {{ $ba->bank_account_name }}</div>@endif
                                    @if($ba->bank_account_number)
                                        <div>
                                            <strong>Account no:</strong>
                                            <span class="font-monospace">{{ $ba->bank_account_number }}</span>
                                            <button class="btn btn-sm btn-outline-secondary ms-2" onclick="navigator.clipboard.writeText('{{ $ba->bank_account_number }}')">Copy</button>
                                            @if($ba->is_default)<span class="badge bg-primary ms-2">Default</span>@endif
                                        </div>
                                    @endif
                                    @if($ba->instructions)<div class="text-muted small">{{ $ba->instructions }}</div>@endif
                                </div>
                            @endforeach
                        </dd>
                    @endif
                @endif
            @endif
            <dt class="col-4">Payment</dt><dd class="col-8"><span class="badge text-bg-light">{{ ucfirst($order->payment_status) }}</span></dd>
            @if($order->payment_method)
                <dt class="col-4">Payment Method</dt><dd class="col-8">{{ ucfirst(str_replace('_', ' ', $order->payment_method)) }}</dd>
            @endif
            <dt class="col-4">Note</dt><dd class="col-8">{{ $order->note ?: '-' }}</dd>
        </dl>
    </div></div></div>
    <div class="col-md-6"><div class="card"><div class="card-body">
        <dl class="row mb-2">
            <dt class="col-6">Subtotal</dt><dd class="col-6 text-end">₦{{ number_format($order->subtotal, 2) }}</dd>
            <dt class="col-6">Discount</dt><dd class="col-6 text-end">{{ number_format($order->discount, 2) }}%</dd>
            @php
                $shippingFee = (float) $order->shipping_fee;
                $shippingDiscountPct = (float) \App\Models\Setting::get('shipping_discount', 0);
                $shippingDiscountAmount = $shippingFee * ($shippingDiscountPct / 100);
                $totalShipping = $shippingFee - $shippingDiscountAmount;
                $orderDiscountAmount = $order->subtotal * ((float) $order->discount / 100);
                $totalAmount = $order->subtotal - $orderDiscountAmount + $totalShipping;
            @endphp
            <dt class="col-6">Shipping</dt>
            <dd class="col-6 text-end">
                <span class="text-muted small d-block">₦{{ number_format($shippingFee, 2) }} per order</span>
                <strong>₦{{ number_format($totalShipping, 2) }}</strong>
                @if($shippingDiscountPct > 0)
                    <small class="text-success d-block">-{{ number_format($shippingDiscountPct, 0) }}% discount: -₦{{ number_format($shippingDiscountAmount, 2) }}</small>
                @endif
            </dd>
            <dt class="col-6 fw-bold">Total Amount</dt><dd class="col-6 text-end fw-bold">₦{{ number_format($totalAmount, 2) }}</dd>
            <dt class="col-6">Paid</dt><dd class="col-6 text-end">₦{{ number_format($order->amount_paid, 2) }}</dd>
            <dt class="col-6 text-danger">Balance</dt><dd class="col-6 text-end text-danger">₦{{ number_format($order->balance, 2) }}</dd>
        </dl>
        <hr class="my-2">
        <div class="d-flex align-items-center justify-content-between gap-2">
            <div>
                <div class="small text-muted mb-1"><i class="bi bi-calendar-event me-1"></i>Expected Delivery</div>
                <div class="fw-semibold">
                    @if($order->expected_delivery_date)
                        {{ $order->expected_delivery_date->format('M d, Y g:i A') }}
                        <small class="text-muted">({{ $order->expected_delivery_date->diffForHumans() }})</small>
                    @else
                        <span class="text-muted">Not set</span>
                    @endif
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse"
                    data-bs-target="#delivery-date-form">
                <i class="bi bi-pencil"></i>
            </button>
        </div>
        <div class="collapse mt-2" id="delivery-date-form">
            <form method="post" action="{{ route('admin.orders.delivery-date.update', $order) }}"
                  class="d-flex gap-2 align-items-end">
                @csrf @method('PATCH')
                <div class="flex-grow-1">
                    <label class="form-label form-label-sm mb-1">New expected date</label>
                    <input type="date" name="expected_delivery_date"
                           class="form-control form-control-sm"
                           value="{{ $order->expected_delivery_date?->toDateString() ?? now()->addDays(7)->toDateString() }}"
                           min="{{ $order->order_date->toDateString() }}">
                </div>
                <button class="btn btn-sm btn-primary">Save</button>
            </form>
        </div>
    </div></div></div>
</div>

<div class="card mb-3"><div class="card-header">Items</div>
<table class="table mb-0">
    <thead><tr><th>Product</th><th>Qty</th><th>Unit Price</th><th>Discount %</th><th class="text-end">Line Total</th></tr></thead>
    <tbody>
    @foreach($order->items as $it)
        <tr>
            <td>
                {{ $it->product->name }}
                @if($it->variant && $it->variant->options_label)
                    <small class="text-muted d-block">{{ $it->variant->options_label }}</small>
                @endif
                @if($it->selected_size)
                    <small class="text-muted d-block">Size: {{ $it->selected_size }}</small>
                @endif
                @if($it->selected_age_group)
                    <small class="text-muted d-block">Age: {{ $it->selected_age_group }}</small>
                @endif
            </td>
            <td>{{ $it->quantity }}</td>
            <td>
                @if($it->deal_id)
                    <span class="price-old d-block">₦{{ number_format($it->original_unit_price, 2) }}</span>
                    ₦{{ number_format($it->unit_price, 2) }}
                    <span class="badge bg-danger-subtle text-danger d-inline-block mt-1">Deal</span>
                @else
                    ₦{{ number_format($it->unit_price, 2) }}
                @endif
            </td>
            <td>
                @if($it->deal_id)
                    @if($it->discount_amount > 0)
                        -₦{{ number_format($it->discount_amount, 2) }}
                    @else
                        Deal
                    @endif
                @else
                    {{ number_format($it->discount, 2) }}%
                @endif
            </td>
            <td class="text-end">₦{{ number_format($it->line_total, 2) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>

@php
    $statusTimeline = array_filter([
        'Ordered'              => $order->ordered_at,
        'Pending Confirmation' => $order->pending_confirmation_at,
        'Pending Payment'      => $order->pending_payment_at,
        'Confirmed'            => $order->confirmed_at,
        'Processing'           => $order->processing_at,
        'Shipping to Station'  => $order->shipped_at,
        'Out for Delivery'     => $order->shipped_at,
        'Ready for Pick Up'    => $order->ready_for_pickup_at,
        'Delivered'            => $order->delivered_at,
        'Cancelled'            => $order->cancelled_at,
        'Expired'              => $order->expired_at,
        'Pickup Window Expired'=> $order->pickup_window_expired_at,
    ], fn ($v) => $v !== null);
@endphp

@if($statusTimeline)
<div class="card mb-3">
    <div class="card-header"><i class="bi bi-clock-history me-1"></i>Status Timeline</div>
    <div class="card-body p-3">
        <ul class="list-group list-group-flush">
            @foreach($statusTimeline as $label => $at)
                <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-1">
                    <span>{{ $label }}</span>
                    <span class="small text-muted">{{ $at->format('M d, Y g:i A') }}</span>
                </li>
            @endforeach
        </ul>
    </div>
</div>
@endif

<div class="card mb-3"><div class="card-header d-flex justify-content-between">
    <span>Payments</span>
    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="collapse" data-bs-target="#pay-form">Record Payment</button>
</div>
<div class="collapse" id="pay-form">
    <div class="card-body bg-light">
        <form method="post" action="{{ route('admin.orders.payments.store', $order) }}">
            @csrf
            <div class="row g-2">
                <div class="col-md-3"><input type="date" name="payment_date" value="{{ now()->toDateString() }}" class="form-control" required></div>
                <div class="col-md-2"><input type="number" step="0.01" name="amount" placeholder="Amount" class="form-control" required></div>
                <div class="col-md-2">
                    <select name="method" class="form-select">
                        @foreach(['cash','card','transfer','mobile','other'] as $m)
                            <option value="{{ $m }}">{{ ucfirst($m) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3"><input name="transaction_id" placeholder="Transaction ID" class="form-control"></div>
                <div class="col-md-2"><button class="btn btn-primary w-100">Save</button></div>
            </div>
        </form>
    </div>
</div>
<table class="table mb-0">
    <thead><tr><th>Reference</th><th>Date</th><th>Method</th><th>Transaction</th><th class="text-end">Amount</th></tr></thead>
    <tbody>
    @forelse($order->payments as $p)
        <tr>
            <td>{{ $p->reference }}</td>
            <td>{{ $p->payment_date->format('Y-m-d H:i') }}</td>
            <td>{{ $p->method }}</td>
            <td>{{ $p->transaction_id ?? '-' }}</td>
            <td class="text-end">₦{{ number_format($p->amount, 2) }}</td>
        </tr>
    @empty
        <tr><td colspan="5" class="text-center text-muted p-3">No payments recorded.</td></tr>
    @endforelse
    </tbody>
</table>
</div>

    @if($order->payment_status === 'verification_pending')
        @php $verification = $order->latestPendingVerification(); @endphp
        <div class="card border-warning mt-2">
            <div class="card-header bg-warning bg-opacity-10">
                <i class="bi bi-clock-history me-1"></i>
                <strong>Payment Verification Pending</strong>
                @if($verification)
                    <span class="small text-muted ms-2">Submitted {{ $verification->submitted_at->diffForHumans() }}</span>
                @endif
            </div>
            <div class="card-body">
                @if($verification)
                    <div class="small text-muted mb-3">
                        <div>Station: {{ $verification->station?->name ?? '-' }}</div>
                        <div>Amount: ₦{{ number_format($order->grand_total, 2) }}</div>
                        @if($verification->station_note)
                            <div>Note from station: {{ $verification->station_note }}</div>
                        @endif
                    </div>
                @endif
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#confirmPaymentModal">
                        <i class="bi bi-check-circle me-1"></i>Confirm Payment
                    </button>
                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectPaymentModal">
                        <i class="bi bi-x-circle me-1"></i>Reject Payment
                    </button>
                </div>
            </div>
        </div>

        {{-- Confirm Modal --}}
        <div class="modal fade" id="confirmPaymentModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="post" action="{{ route('admin.orders.confirm-payment', $order) }}">
                        @csrf
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title">Confirm Payment</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>Confirm that <strong>₦{{ number_format($order->grand_total, 2) }}</strong> has been received for order <strong>{{ $order->reference }}</strong>?</p>
                            <p class="text-muted small">This will set payment status to Paid and notify the station.</p>
                            <div class="mb-3">
                                <label class="form-label">Note (optional)</label>
                                <textarea name="admin_note" class="form-control" rows="2" placeholder="Any notes for the station..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success">Confirm Payment</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Reject Modal --}}
        <div class="modal fade" id="rejectPaymentModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="post" action="{{ route('admin.orders.reject-payment', $order) }}">
                        @csrf
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title">Reject Payment</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>Reject the payment verification for order <strong>{{ $order->reference }}</strong>?</p>
                            <p class="text-muted small">This will notify the station that the payment was not verified.</p>
                            <div class="mb-3">
                                <label class="form-label">Reason (optional)</label>
                                <textarea name="admin_note" class="form-control" rows="2" placeholder="Why is this payment being rejected?"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger">Reject Payment</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @elseif($order->payment_status !== 'paid' && ! in_array($order->status, ['cancelled', 'expired']))
        <div class="mt-2">
            <form method="post" action="{{ route('admin.orders.mark-paid', $order) }}">
                @csrf
                <button class="btn btn-sm btn-success">Mark Order As Paid</button>
            </form>
        </div>
    @endif

    {{-- Cancellation refund status (auto-created by OrderService::cancel) --}}
    @php
        $cancellationRefunds = $order->refundRequests()->whereIn('status', ['refund_required', 'refund_approved', 'refund_processing', 'refund_failed'])->latest()->get();
    @endphp
    @if($cancellationRefunds->count())
        <div class="card mt-2 border-info">
            <div class="card-header bg-info bg-opacity-10">
                <i class="bi bi-cash-stack me-1"></i>
                <strong>Refund</strong> — Auto-created on cancellation
            </div>
            <div class="card-body">
                @foreach($cancellationRefunds as $rr)
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <span class="badge {{ match($rr->status) {
                                'refund_required' => 'bg-warning text-dark',
                                'refund_approved' => 'bg-primary',
                                'refund_processing' => 'bg-info',
                                'refund_failed' => 'bg-danger',
                                default => 'bg-secondary'
                            } }}">
                                {{ ucfirst(str_replace('_', ' ', $rr->status)) }}
                            </span>
                            <span class="ms-2">₦{{ number_format($rr->amount, 2) }}</span>
                            <small class="text-muted ms-2">{{ $rr->created_at->diffForHumans() }}</small>
                        </div>
                        <div class="d-flex gap-1">
                            @if($rr->status === 'refund_required')
                                <form method="post" action="{{ route('admin.refunds.approve-refund', $rr) }}" style="display:inline">
                                    @csrf
                                    <button class="btn btn-sm btn-success">Approve</button>
                                </form>
                                <form method="post" action="{{ route('admin.refunds.retry-refund', $rr) }}" style="display:inline">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-primary">Retry</button>
                                </form>
                                <form method="post" action="{{ route('admin.refunds.sync-refund', $rr) }}" style="display:inline">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-info">Sync</button>
                                </form>
                            @elseif($rr->status === 'refund_failed')
                                <form method="post" action="{{ route('admin.refunds.retry-refund', $rr) }}" style="display:inline">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-primary">Retry</button>
                                </form>
                                <form method="post" action="{{ route('admin.refunds.sync-refund', $rr) }}" style="display:inline">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-info">Sync</button>
                                </form>
                            @elseif($rr->status === 'refund_processing')
                                <form method="post" action="{{ route('admin.refunds.sync-refund', $rr) }}" style="display:inline">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-info">Sync</button>
                                </form>
                            @endif
                            <a href="{{ route('admin.refunds.show', $rr) }}" class="btn btn-sm btn-outline-secondary">View</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if($order->payment_status === 'under_review')
        @php $underReviewTxn = $order->paymentTransactions()->where('status', 'under_review')->latest()->first(); @endphp
        <div class="card border-warning mt-2">
            <div class="card-header bg-warning bg-opacity-10">
                <i class="bi bi-hourglass-split me-1"></i>
                <strong>Payment Under Review</strong>
                @if($underReviewTxn)
                    <span class="small text-muted ms-2">Reference: {{ $underReviewTxn->reference }}</span>
                @endif
            </div>
            <div class="card-body">
                @if($underReviewTxn && ($underReviewTxn->opay_payload['review_reason'] ?? null))
                    <div class="small text-muted mb-3">
                        <div><strong>Reason:</strong> {{ $underReviewTxn->opay_payload['review_reason'] }}</div>
                        <div><strong>Amount:</strong> ₦{{ number_format($order->grand_total, 2) }}</div>
                        <div><strong>Customer:</strong> {{ $order->customer?->name ?? '-' }}</div>
                    </div>
                @endif
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#confirmUnderReviewModal">
                        <i class="bi bi-check-circle me-1"></i>Confirm Payment
                    </button>
                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectUnderReviewModal">
                        <i class="bi bi-x-circle me-1"></i>Reject Payment
                    </button>
                </div>
            </div>
        </div>

        {{-- Confirm Under Review Modal --}}
        <div class="modal fade" id="confirmUnderReviewModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="post" action="{{ route('admin.orders.confirm-under-review', $order) }}">
                        @csrf
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title">Confirm Payment</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>Confirm that <strong>₦{{ number_format($order->grand_total, 2) }}</strong> has been received for order <strong>{{ $order->reference }}</strong>?</p>
                            <p class="text-muted small">This will mark the order as paid and confirm it.</p>
                            <div class="mb-3">
                                <label class="form-label">Note (optional)</label>
                                <textarea name="admin_note" class="form-control" rows="2" placeholder="Reason for confirming..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success">Confirm Payment</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Reject Under Review Modal --}}
        <div class="modal fade" id="rejectUnderReviewModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="post" action="{{ route('admin.orders.reject-under-review', $order) }}">
                        @csrf
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title">Reject Payment</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>Reject the payment for order <strong>{{ $order->reference }}</strong>?</p>
                            <p class="text-muted small">The customer will be notified and can retry payment.</p>
                            <div class="mb-3">
                                <label class="form-label">Reason (optional)</label>
                                <textarea name="admin_note" class="form-control" rows="2" placeholder="Why is this payment being rejected?"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger">Reject Payment</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

@if($order->paymentTransactions->isNotEmpty())
<div class="card mb-3">
    <div class="card-header"><i class="bi bi-bank me-1"></i>Paystack Bank Transfer Transactions</div>
    <table class="table mb-0 table-sm">
        <thead class="table-light">
            <tr>
                <th>Reference</th>
                <th>Virtual Account</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Expires / Queried</th>
            </tr>
        </thead>
        <tbody>
        @foreach($order->paymentTransactions as $txn)
            <tr>
                <td class="font-monospace small">{{ $txn->reference }}</td>
                <td>
                    @if($txn->virtual_account_number)
                        <span class="font-monospace fw-bold">{{ $txn->virtual_account_number }}</span>
                        <small class="text-muted d-block">{{ $txn->virtual_bank_name }}</small>
                    @else
                        <span class="text-muted">-</span>
                    @endif
                </td>
                <td>₦{{ number_format($txn->amount, 2) }}</td>
                <td>
                    @php
                        $badge = match($txn->status) {
                            'success'     => 'bg-success',
                            'pending'     => 'bg-warning text-dark',
                            'failed'      => 'bg-danger',
                            'expired'     => 'bg-secondary',
                            'cancelled'   => 'bg-secondary',
                            'under_review'=> 'bg-warning text-dark',
                            default       => 'bg-light text-dark',
                        };
                    @endphp
                    <span class="badge {{ $badge }}">{{ ucfirst($txn->status) }}</span>
                </td>
                <td class="small text-muted">
                    @if($txn->expires_at)
                        Exp: {{ $txn->expires_at->format('M d H:i') }}
                    @endif
                    @if($txn->last_queried_at)
                        <br>Checked: {{ $txn->last_queried_at->diffForHumans() }}
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif

@if($order->isForDelivery())
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-truck me-1"></i>Courier / Delivery Info</span>
        <button class="btn btn-sm btn-outline-secondary" type="button"
                data-bs-toggle="collapse" data-bs-target="#courier-form">
            <i class="bi bi-pencil"></i> {{ $order->courier_name ? 'Update' : 'Add' }}
        </button>
    </div>
    <div class="card-body {{ $order->courier_name ? '' : 'collapse' }}" id="courier-form-display">
        @if($order->courier_name)
            <dl class="row mb-0">
                <dt class="col-4">Courier</dt>
                <dd class="col-8">{{ $order->courier_name }}</dd>
                @if($order->tracking_number)
                    <dt class="col-4">Tracking No.</dt>
                    <dd class="col-8 font-monospace">{{ $order->tracking_number }}</dd>
                @endif
                @if($order->tracking_url)
                    <dt class="col-4">Track Link</dt>
                    <dd class="col-8">
                        <a href="{{ $order->tracking_url }}" target="_blank">
                            {{ $order->tracking_url }} <i class="bi bi-box-arrow-up-right ms-1"></i>
                        </a>
                    </dd>
                @endif
            </dl>
        @else
            <p class="text-muted small mb-0">No courier assigned yet.</p>
        @endif
    </div>
    <div class="collapse {{ $order->courier_name ? '' : 'show' }}" id="courier-form">
        <div class="card-body border-top bg-light">
            <form method="post" action="{{ route('admin.orders.courier.update', $order) }}">
                @csrf @method('PATCH')
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label form-label-sm">Courier Name *</label>
                        <input type="text" name="courier_name" class="form-control form-control-sm"
                               value="{{ old('courier_name', $order->courier_name) }}"
                               placeholder="e.g. GIG Logistics, Kwik, John Doe" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label form-label-sm">Tracking Number</label>
                        <input type="text" name="tracking_number" class="form-control form-control-sm"
                               value="{{ old('tracking_number', $order->tracking_number) }}"
                               placeholder="e.g. GIG123456789">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label form-label-sm">Tracking URL <small class="text-muted">(optional)</small></label>
                        <input type="url" name="tracking_url" class="form-control form-control-sm"
                               value="{{ old('tracking_url', $order->tracking_url) }}"
                               placeholder="https://track.giglogistics.com/...">
                    </div>
                </div>
                <div class="mt-3 d-flex gap-2">
                    <button class="btn btn-sm btn-primary">Save Courier Info</button>
                    @if($order->courier_name)
                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                data-bs-toggle="collapse" data-bs-target="#courier-form">Cancel</button>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@endsection

@push('scripts')
<script>
document.getElementById('statusJumpSelect')?.addEventListener('change', function() {
    if (this.value && confirm('Update order status to "' + this.options[this.selectedIndex].text + '"?')) {
        this.form.submit();
    } else {
        this.selectedIndex = 0;
    }
});
</script>
@endpush
