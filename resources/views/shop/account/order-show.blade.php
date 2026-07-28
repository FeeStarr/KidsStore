@extends('layouts.shop', ['title' => 'Order '.$order->reference])
@section('content')

<div class="d-flex justify-content-between mb-3">
    <h3>Order {{ $order->reference }}</h3>
    <a href="{{ route('shop.account.orders.index') }}" class="btn btn-outline-secondary btn-sm">Back to orders</a>
</div>

@if(false && session('show_pay_now') && $order->payment_status !== 'paid' && ! in_array($order->status, ['cancelled']))
<div class="card border-primary mb-3" id="pay-now-panel">
    <div class="card-body text-center py-4">
        <h5 class="mb-2"><i class="bi bi-shield-lock me-2"></i>Complete Your Payment</h5>
        <p class="text-muted small mb-3">Transfer the exact amount to the virtual account below. Payment is verified automatically.</p>
        <div id="pay-now-loading">
            <div class="spinner-border text-primary mb-2" role="status"></div>
            <div class="small text-muted">Generating your payment account...</div>
        </div>
        <div id="pay-now-account" style="display:none">
            <div class="row g-3 mb-3 justify-content-center">
                <div class="col-sm-5">
                    <div class="bg-light rounded p-3">
                        <div class="small text-muted mb-1">Bank</div>
                        <div class="fw-bold fs-5" id="pn-bank-name"></div>
                    </div>
                </div>
                <div class="col-sm-5">
                    <div class="bg-light rounded p-3">
                        <div class="small text-muted mb-1">Account Number</div>
                        <div class="fw-bold fs-3 font-monospace" id="pn-account-number"></div>
                        <button type="button" class="btn btn-sm btn-outline-secondary mt-1" onclick="navigator.clipboard.writeText(document.getElementById('pn-account-number').textContent);this.textContent='Copied!'">Copy</button>
                    </div>
                </div>
            </div>
            <div class="mb-2">
                <span class="fw-bold fs-5 text-primary">&#8358;{{ number_format($order->grand_total, 2) }}</span>
            </div>
            <div class="small text-muted mb-3">
                Expires in <strong id="pn-countdown">...</strong>
            </div>
            <button id="pn-check-btn" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-arrow-clockwise me-1"></i>I've paid — check now
            </button>
            <div id="pn-status" class="small text-muted mt-2"></div>
        </div>
        <div id="pay-now-error" style="display:none" class="text-danger small"></div>
    </div>
</div>
@endif

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <h6>Status</h6>
            <p class="mb-1">
                <span class="badge text-bg-light">{{ $order->getStatusLabel() }}</span>
                &middot;
                <span class="badge text-bg-light">{{ ucfirst($order->payment_status) }}</span>
            </p>
            <small class="text-muted">Placed on {{ $order->order_date->format('M d, Y') }}</small>

            @if(! in_array($order->status, ['delivered', 'cancelled']))
                <div class="mt-2 p-2 bg-light rounded small">
                    <i class="bi bi-calendar-check me-1 text-primary"></i>
                    <strong>Estimated delivery:</strong>
                    {{ $order->delivery_window }}
                </div>
            @endif

            <div class="mt-2 small text-muted">
                <i class="bi bi-{{ $order->isForPickup() ? 'geo-alt' : 'house-door' }} me-1"></i>
                {{ $order->getDeliveryMethodLabel() }}
                @if($order->isForPickup() && $order->pickupStation)
                    — {{ $order->pickupStation->name }}
                @elseif($order->isForDelivery() && $order->delivery_address)
                    — {{ Str::limit($order->delivery_address, 60) }}
                @endif
            </div>

            @if($order->isForDelivery() && $order->courier_name)
                <div class="mt-2 p-2 bg-light rounded small">
                    <i class="bi bi-truck me-1 text-primary"></i>
                    <strong>{{ $order->courier_name }}</strong>
                    @if($order->tracking_number)
                        &nbsp;·&nbsp; Tracking: <span class="font-monospace fw-semibold">{{ $order->tracking_number }}</span>
                        @if($order->tracking_url)
                            <a href="{{ $order->tracking_url }}" target="_blank" class="ms-2 btn btn-sm btn-outline-primary py-0 px-2">
                                <i class="bi bi-box-arrow-up-right me-1"></i>Track Package
                            </a>
                        @endif
                    @endif
                </div>
            @endif
        </div></div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <h6>Totals</h6>
            <dl class="row mb-0">
                <dt class="col-6">Subtotal</dt><dd class="col-6 text-end">&#8358;{{ number_format($order->subtotal, 2) }}</dd>
                <dt class="col-6">Discount</dt><dd class="col-6 text-end">{{ number_format($order->discount, 2) }}%</dd>
                @php
                    $totalQuantity = $order->items->sum('quantity');
                    $shippingFeePerItem = (float) $order->shipping_fee;
                    $totalShippingBeforeDiscount = $shippingFeePerItem * $totalQuantity;
                    $shippingDiscountPct = (float) \App\Models\Setting::get('shipping_discount', 0);
                    $shippingDiscountAmount = $totalShippingBeforeDiscount * ($shippingDiscountPct / 100);
                    $totalShipping = $totalShippingBeforeDiscount - $shippingDiscountAmount;
                    $orderDiscountAmount = $order->subtotal * ((float) $order->discount / 100);
                    $totalAmount = $order->subtotal - $orderDiscountAmount + $totalShipping;
                @endphp
                <dt class="col-6">Shipping</dt>
                <dd class="col-6 text-end">
                    <span class="text-muted small d-block">&#8358;{{ number_format($shippingFeePerItem, 2) }} × {{ $totalQuantity }} item(s)</span>
                    <strong>&#8358;{{ number_format($totalShipping, 2) }}</strong>
                    @if($shippingDiscountPct > 0)
                        <small class="text-success d-block">-{{ number_format($shippingDiscountPct, 0) }}% discount: -&#8358;{{ number_format($shippingDiscountAmount, 2) }}</small>
                    @endif
                </dd>
                <dt class="col-6 fw-bold">Total Amount</dt><dd class="col-6 text-end fw-bold">&#8358;{{ number_format($totalAmount, 2) }}</dd>
                <dt class="col-6">Paid</dt><dd class="col-6 text-end">&#8358;{{ number_format($order->amount_paid, 2) }}</dd>
            </dl>
        </div></div>
    </div>
</div>

@endphp

<div class="card border-0 shadow-sm">
<table class="table mb-0 align-middle">
    <thead><tr><th colspan="2">Item</th><th>Qty</th><th class="text-end">Unit</th><th class="text-end">Line Total</th></tr></thead>
    <tbody>
    @foreach($order->items as $it)
        <tr>
            <td style="width:80px">
                @if($it->product?->primaryImage)
                    <img src="{{ $it->product->primaryImage->url }}" style="width:56px;height:56px;object-fit:cover;border-radius:.35rem">
                @endif
            </td>
            <td>
                @if($it->product)
                    <a href="{{ route('shop.products.show', $it->product) }}">{{ $it->product->name }}</a>
                @else
                    {{ $it->product_id }}
                @endif
                @if($it->variant && $it->variant->options_label)
                    <div class="small text-muted">{{ $it->variant->options_label }}</div>
                @endif
                @if($it->selected_size)
                    <div class="small text-muted">Size: {{ $it->selected_size }}</div>
                @endif
                @if($it->selected_age_group)
                    <div class="small text-muted">Age: {{ $it->selected_age_group }}</div>
                @endif
            </td>
            <td>{{ $it->quantity }}</td>
            <td class="text-end">&#8358;{{ number_format($it->unit_price, 2) }}</td>
            <td class="text-end">&#8358;{{ number_format($it->line_total, 2) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>

@php
    $canRefund       = $order->status === 'delivered'
                       && $order->updated_at->diffInDays(now()) <= \App\Models\RefundRequest::REFUND_WINDOW_DAYS;
    $existingRequests = $order->refundRequests ?? collect();
    // Check per-item: which items already have an active return request
    $activeReturnItemIds = $existingRequests
        ->whereIn('status', ['requested', 'pending_review', 'awaiting_evidence', 'approved', 'awaiting_shipment'])
        ->pluck('order_item_id')
        ->filter()
        ->toArray();
    $hasAnyActiveReturn = $existingRequests->whereIn('status', ['requested', 'pending_review', 'awaiting_evidence', 'approved', 'awaiting_shipment'])->isNotEmpty();
    // Full order return is active if there's a pending request with null order_item_id
    $fullOrderReturnActive = $existingRequests->whereIn('status', ['requested', 'pending_review', 'awaiting_evidence', 'approved', 'awaiting_shipment'])->contains('order_item_id', null);
@endphp

@if($canRefund)
<div class="card border-0 shadow-sm mt-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-arrow-counterclockwise me-1"></i>Return Request</span>
        @if(! $fullOrderReturnActive)
            <button class="btn btn-sm btn-outline-warning" type="button"
                    data-bs-toggle="collapse" data-bs-target="#refund-form">
                Request a Refund
            </button>
        @endif
    </div>

    @if($existingRequests->isNotEmpty())
    <div class="card-body border-bottom">
        <div class="small text-muted mb-2">Your return requests for this order:</div>
        @foreach($existingRequests as $rr)
            @php
                $rbadge = match($rr->status) {
                    'requested', 'pending_review'    => 'bg-warning text-dark',
                    'awaiting_evidence'              => 'bg-warning text-dark',
                    'approved', 'awaiting_shipment', 'in_transit' => 'bg-primary',
                    'received', 'inspection'         => 'bg-secondary',
                    'refund_approved', 'refund_processing' => 'bg-info',
                    'refunded', 'completed', 'replacement_delivered' => 'bg-success',
                    'rejected', 'cancelled'          => 'bg-danger',
                    'refund_failed'                  => 'bg-dark',
                    default                          => 'bg-secondary',
                };
            @endphp
            <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                <div>
                    <span class="small fw-semibold">{{ $rr->getScopeLabel() }}</span>
                    <span class="small text-muted ms-2">— {{ $rr->reason_label }}</span>
                </div>
                <div class="text-end">
                    <span class="badge {{ $rbadge }}">{{ ucfirst(str_replace('_', ' ', $rr->status)) }}</span>
                    <div class="small fw-bold">₦{{ number_format($rr->amount, 2) }}</div>
                </div>
            </div>
            @if($rr->admin_note)
                <div class="small text-muted mt-1"><i class="bi bi-chat-left-text me-1"></i>{{ $rr->admin_note }}</div>
            @endif

            {{-- Evidence upload form for awaiting_evidence status --}}
            @if($rr->status === 'awaiting_evidence')
                <div class="mt-2 p-3 bg-light rounded">
                    <div class="small fw-semibold mb-2"><i class="bi bi-camera me-1"></i>Additional evidence needed</div>
                    <form method="post" action="{{ route('shop.refund.evidence', [$order, $rr]) }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-2">
                            <input type="file" name="evidence" class="form-control form-control-sm" accept="image/*" required>
                            <div class="form-text">Upload a clear photo of the item (max 5MB).</div>
                        </div>
                        <div class="mb-2">
                            <textarea name="details" rows="2" class="form-control form-control-sm"
                                      placeholder="Additional details (required for some reasons)"></textarea>
                        </div>
                        <button class="btn btn-sm btn-primary"><i class="bi bi-upload me-1"></i>Upload Evidence</button>
                    </form>
                </div>
            @endif

            {{-- Cancel button for active requests --}}
            @if(in_array($rr->status, ['requested', 'pending_review', 'awaiting_evidence']))
                <div class="mt-2">
                    <form method="post" action="{{ route('shop.refund.cancel', [$order, $rr]) }}" class="d-inline"
                          onsubmit="return confirm('Cancel this return request?')">
                        @csrf
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-circle me-1"></i>Cancel Request</button>
                    </form>
                </div>
            @endif
        @endforeach
    </div>
    @endif

    @if(! $fullOrderReturnActive)
    <div class="collapse" id="refund-form">
        <div class="card-body">
            <form method="post" action="{{ route('shop.refund.store', $order) }}"
                  enctype="multipart/form-data">
                @csrf

                @php
                    $returnableItems = $order->items->filter(fn ($i) => $i->product && $i->product->is_returnable);
                    $allNonReturnable = $returnableItems->isEmpty() && $order->items->isNotEmpty();
                    $hasReturnable = $returnableItems->isNotEmpty();
                @endphp

                @if($allNonReturnable)
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        None of the items in this order are eligible for returns or refunds.
                    </div>
                @else
                {{-- Scope --}}
                <div class="mb-3">
                    <label class="form-label">What would you like to refund? *</label>
                    @if($hasReturnable)
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="scope"
                               id="scope_full" value="full" checked>
                        <label class="form-check-label" for="scope_full">
                            Full order — ₦{{ number_format($order->amount_paid, 2) }}
                        </label>
                    </div>
                    @endif
                    @foreach($order->items as $it)
                        @php
                            $isReturnable = $it->product && $it->product->is_returnable;
                            $hasActiveReturn = in_array($it->id, $activeReturnItemIds);
                        @endphp
                        <div class="form-check">
                            <input class="form-check-input scope-item-radio" type="radio" name="scope"
                                   id="scope_item_{{ $it->id }}" value="item"
                                   data-item-id="{{ $it->id }}"
                                   data-item-qty="{{ $it->quantity }}"
                                   {{ (!$isReturnable || $hasActiveReturn) ? 'disabled' : '' }}>
                            <label class="form-check-label" for="scope_item_{{ $it->id }}">
                                {{ $it->product?->name }}
                                @if($it->variant?->options_label) — {{ $it->variant->options_label }}@endif
                                (×{{ $it->quantity }}) — ₦{{ number_format($it->line_total, 2) }}
                                @if(!$isReturnable)
                                    <span class="badge bg-secondary ms-1" style="font-size:10px;">Non-returnable</span>
                                @elseif($hasActiveReturn)
                                    <span class="badge bg-warning text-dark ms-1" style="font-size:10px;">Return in progress</span>
                                @endif
                            </label>
                        </div>
                    @endforeach
                </div>
                @endif

                {{-- Hidden item fields, shown when item radio selected --}}
                <div id="item-fields" style="display:none" class="mb-3 ms-4 row g-2">
                    <input type="hidden" name="order_item_id" id="selected-item-id">
                    <div class="col-auto">
                        <label class="form-label form-label-sm">Quantity to refund</label>
                        <input type="number" name="quantity" min="1" value="1"
                               class="form-control form-control-sm" style="width:80px">
                    </div>
                </div>

                {{-- Reason --}}
                <div class="mb-3">
                    <label class="form-label">Reason *</label>
                    <select name="reason" class="form-select" required>
                        <option value="">— Select reason —</option>
                        @foreach(\App\Models\RefundRequest::REASONS as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Additional details</label>
                    <textarea name="details" rows="3" class="form-control"
                              placeholder="Please describe the issue…"></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Evidence photo <small class="text-muted">(optional — for damaged/wrong item)</small></label>
                    <input type="file" name="evidence" class="form-control" accept="image/*">
                    <div class="form-text">Max 5 MB. JPG/PNG/WEBP.</div>
                </div>

                <div class="alert alert-light border small mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    Refunds are reviewed within 2–3 business days. Approved refunds are credited to your original payment method within 5–7 working days.
                </div>

                <button class="btn btn-warning">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Submit Refund Request
                </button>
            </form>
        </div>
    </div>
    @endif
</div>
@endif

@push('scripts')
<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    // ── Pay Now panel ──────────────────────────────────────────────────────
    const payPanel = document.getElementById('pay-now-panel');
    if (payPanel) {
        const loading  = document.getElementById('pay-now-loading');
        const account  = document.getElementById('pay-now-account');
        const errDiv   = document.getElementById('pay-now-error');
        const bankName = document.getElementById('pn-bank-name');
        const acctNum  = document.getElementById('pn-account-number');
        const countdown = document.getElementById('pn-countdown');
        const checkBtn = document.getElementById('pn-check-btn');
        const status   = document.getElementById('pn-status');

        // Auto-initiate payment
        fetch('{{ route("shop.opay.initiate", $order) }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                loading.style.display = 'none';
                account.style.display = '';
                bankName.textContent = data.virtual_bank_name || 'OPay';
                acctNum.textContent = data.virtual_account_number || '0000000000';

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

                // Auto-poll every 30s
                setInterval(function() {
                    if (seconds > 0) checkPayment();
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
            errDiv.textContent = 'Network error. Please refresh the page.';
        });

        // Manual check
        if (checkBtn) {
            checkBtn.addEventListener('click', checkPayment);
        }

        function checkPayment() {
            if (checkBtn) { checkBtn.disabled = true; checkBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Checking...'; }
            if (status) status.textContent = '';

            fetch('{{ route("shop.opay.query", $order) }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.paid || data.payment_status === 'paid') {
                    payPanel.innerHTML = '<div class="card-body text-center py-4"><h5 class="text-success"><i class="bi bi-check-circle me-2"></i>Payment Confirmed!</h5><p class="text-muted small">Your payment has been received. Redirecting...</p></div>';
                    setTimeout(function() { window.location.reload(); }, 2000);
                    return;
                }
                if (status) {
                    status.textContent = data.throttled
                        ? 'Please wait before checking again.'
                        : 'Payment not yet received. Transfer then try again.';
                }
            })
            .catch(function() {
                if (status) status.textContent = 'Network error.';
            })
            .finally(function() {
                if (checkBtn) { checkBtn.disabled = false; checkBtn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i>I\'ve paid — check now'; }
            });
        }
    }

    // ── Refund form: toggle item fields on scope selection ────────────────────
    document.querySelectorAll('input[type="radio"][name="scope"]').forEach(radio => {
        radio.addEventListener('change', () => {
            const itemFields  = document.getElementById('item-fields');
            const itemIdInput = document.getElementById('selected-item-id');
            if (!itemFields) return;
            if (radio.value === 'item') {
                itemFields.style.display = '';
                if (itemIdInput && radio.dataset.itemId) {
                    itemIdInput.value = radio.dataset.itemId;
                    const maxQty = radio.dataset.itemQty;
                    const qtyInput = document.querySelector('[name="quantity"]');
                    if (maxQty && qtyInput) {
                        qtyInput.max = maxQty;
                        if (parseInt(qtyInput.value) > parseInt(maxQty)) qtyInput.value = maxQty;
                    }
                }
            } else {
                itemFields.style.display = 'none';
                if (itemIdInput) itemIdInput.value = '';
            }
        });
    });
})();
</script>
@endpush

@endsection
