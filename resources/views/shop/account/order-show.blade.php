@extends('layouts.shop', ['title' => 'Order '.$order->reference])
@section('content')

<div class="d-flex justify-content-between mb-3">
    <h3>Order {{ $order->reference }}</h3>
    <a href="{{ route('shop.account.orders.index') }}" class="btn btn-outline-secondary btn-sm">Back to orders</a>
</div>

@if($order->payment_status === 'paid')
<div class="card border-success mb-3" id="payment-success-panel">
    <div class="card-body d-flex align-items-center">
        <div class="me-3">
            <i class="bi bi-check-circle-fill text-success" style="font-size:2.5rem"></i>
        </div>
        <div>
            <h5 class="mb-1 text-success"><i class="bi bi-shield-check me-1"></i>Payment Successful</h5>
            <p class="mb-1">Your payment has been confirmed. Thank you for your purchase!</p>
            <small class="text-muted">
                Amount paid: <strong>&#8358;{{ number_format($order->amount_paid, 2) }}</strong> &middot;
                Order status: <span class="badge text-bg-primary">{{ $order->getStatusLabel() }}</span>
            </small>
        </div>
    </div>
</div>
@endif

@if(($order->payment_method === 'instant_bank_transfer' || session('show_pay_now')) && $order->payment_status !== 'paid' && ! in_array($order->status, ['cancelled', 'expired']))
<div class="card border-primary mb-3" id="pay-now-panel">
    <div class="card-body text-center py-4">
        @if($order->payment_status === 'under_review')
            <h5 class="mb-2"><i class="bi bi-hourglass-split me-2"></i>Payment Under Review</h5>
            <p class="text-muted small mb-3">Your payment is being reviewed. We'll confirm shortly. No further action is needed from you right now.</p>
            <div class="spinner-border text-warning mb-2" role="status"></div>
            <div id="pay-now-review" class="small text-muted" data-order-id="{{ $order->id }}">
                Waiting for admin confirmation...
            </div>
        @else
            <h5 class="mb-2"><i class="bi bi-shield-lock me-2"></i>Complete Your Payment</h5>
            <p class="text-muted small mb-3">Click Pay Now. A secure payment window will open where you can pay with your preferred method.</p>
            <div class="mb-3">
                <span class="fw-bold fs-5 text-primary">&#8358;{{ number_format($order->grand_total, 2) }}</span>
            </div>
            <button type="button" class="btn btn-primary btn-lg px-5" id="pn-pay-btn">
                <i class="bi bi-credit-card me-1"></i>Pay Now
            </button>
            <div id="pn-status" class="small text-muted mt-3"></div>
        @endif
        </div>
        <div id="pay-now-error" style="display:none" class="text-danger small">
            <div id="pay-now-error-msg"></div>
            <div class="mt-3 d-flex gap-2 justify-content-center">
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="location.reload()">
                    <i class="bi bi-arrow-clockwise me-1"></i>Retry Payment
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#changePaymentModal">
                    <i class="bi bi-arrow-left me-1"></i>Change Payment Method
                </button>
            </div>
        </div>
    </div>
</div>
@endif

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <h6>Status</h6>
            <p class="mb-1">
                <span class="badge {{ match($order->status) {
                    'delivered' => 'text-bg-success',
                    'cancelled' => 'text-bg-danger',
                    'expired' => 'text-bg-secondary',
                    'pickup window expired' => 'text-bg-danger',
                    'pending payment' => 'text-bg-warning text-dark',
                    'ready for pick up' => 'text-bg-warning text-dark',
                    'confirmed' => 'text-bg-primary',
                    default => 'text-bg-secondary'
                } }}">{{ $order->getStatusLabel() }}</span>
                &middot;
                <span class="badge {{ match($order->payment_status) {
                    'paid' => 'text-bg-success',
                    'under_review' => 'text-bg-warning text-dark',
                    'verification_pending' => 'text-bg-info text-dark',
                    'verification_failed' => 'text-bg-danger',
                    default => 'text-bg-light'
                } }}">{{ ucfirst(str_replace('_', ' ', $order->payment_status)) }}</span>
                @if($order->payment_method)
                    &middot;
                    <span class="badge text-bg-info">{{ ucfirst(str_replace('_', ' ', $order->payment_method)) }}</span>
                @endif
            </p>
            <small class="text-muted">Placed on {{ $order->order_date->format('M d, Y g:i A') }}</small>

            @if(! in_array($order->status, ['delivered', 'cancelled']))
                <div class="mt-2 p-2 bg-light rounded small">
                    <i class="bi bi-calendar-check me-1 text-primary"></i>
                    <strong>Estimated delivery:</strong>
                    {{ $order->delivery_window }}
                </div>
            @endif

            @if($order->status === 'delivered')
                @php
                    $existingReview = \App\Models\ProductReview::where('customer_id', auth()->id())
                        ->whereIn('product_id', $order->items->pluck('product_id')->filter()->values())
                        ->pluck('product_id');
                    $unreviewedItems = $order->items->filter(fn($i) => $i->product && !$existingReview->contains($i->product_id));
                @endphp
                @if($unreviewedItems->isNotEmpty())
                    <div class="mt-3">
                        @foreach($unreviewedItems as $item)
                            <a href="{{ route('shop.products.show', $item->product) }}#review"
                               class="btn btn-sm btn-outline-success mb-1">
                                <i class="bi bi-star me-1"></i>Review {{ $item->product->name }}
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="mt-3">
                        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>All items reviewed</span>
                    </div>
                @endif
            @endif

            <div class="mt-2 small text-muted">
                <i class="bi bi-{{ $order->isForPickup() ? 'geo-alt' : 'house-door' }} me-1"></i>
                {{ $order->getDeliveryMethodLabel() }}
                @if($order->isForPickup() && $order->pickupStation)
                    - {{ $order->pickupStation->name }}
                @elseif($order->isForDelivery() && $order->delivery_address)
                    - {{ Str::limit($order->delivery_address, 60) }}
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
                    $shippingFee = (float) $order->shipping_fee;
                    $shippingDiscountPct = (float) \App\Models\Setting::get('shipping_discount', 0);
                    $shippingDiscountAmount = $shippingFee * ($shippingDiscountPct / 100);
                    $totalShipping = $shippingFee - $shippingDiscountAmount;
                    $orderDiscountAmount = $order->subtotal * ((float) $order->discount / 100);
                    $totalAmount = $order->subtotal - $orderDiscountAmount + $totalShipping;
                @endphp
                <dt class="col-6">Shipping</dt>
                <dd class="col-6 text-end">
                    <span class="text-muted small d-block">&#8358;{{ number_format($shippingFee, 2) }} per order</span>
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

<div class="card border-0 shadow-sm">
<table id="order-items-table" class="table mb-0 align-middle">
    <thead><tr><th>#</th><th colspan="2">Item</th><th>Qty</th><th class="text-end">Unit</th><th class="text-end">Line Total</th></tr></thead>
    <tbody>
    @foreach($order->items as $it)
        <tr>
            <td>{{ $loop->iteration }}</td>
            <td style="width:80px">
                @if($it->product?->primaryImage)
                    <img src="{{ $it->product->primaryImage->url }}" style="width:56px;height:56px;object-fit:cover;border-radius:.35rem" loading="lazy" decoding="async">
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
    $maxReturnHours = max(array_values(\App\Models\RefundRequest::REASON_TIME_LIMITS));
    $canRefund       = $order->status === 'delivered'
                       && $order->updated_at->diffInHours(now()) <= $maxReturnHours;
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

    // Build variant data for exchange (variants of the same product, in stock)
    $itemVariants = [];
    foreach ($order->items as $it) {
        if (! $it->product || ! $it->product->is_returnable) continue;
        $variants = $it->product->variants()
            ->where('is_active', true)
            ->where('id', '!=', $it->product_variant_id)
            ->with('inventory')
            ->get()
            ->filter(fn ($v) => $v->stock_quantity > 0)
            ->map(fn ($v) => [
                'id' => $v->id,
                'label' => $v->options_label . ' - ₦' . number_format($v->net_price, 2),
                'stock' => $v->stock_quantity,
            ])
            ->values();
        $itemVariants[$it->id] = $variants;
    }
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
                    <span class="small text-muted ms-2">- {{ $rr->reason_label }}</span>
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
                            <label class="form-label form-label-sm">Photo</label>
                            <input type="file" name="evidence" class="form-control form-control-sm" accept="image/*">
                            <div class="form-text">Upload a clear photo of the item (max 5MB).</div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label form-label-sm">Video <small class="text-muted">(if requested by admin)</small></label>
                            <input type="file" name="evidence_video" class="form-control form-control-sm" accept="video/mp4,video/mov,video/avi,video/webm">
                            <div class="form-text">Upload a short video (max 20MB). MP4, MOV, or WebM.</div>
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
                            Full order - ₦{{ number_format($order->grand_total, 2) }}
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
                                @if($it->variant?->options_label) - {{ $it->variant->options_label }}@endif
                                (×{{ $it->quantity }}) - ₦{{ number_format($it->line_total, 2) }}
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
                        <option value="">- Select reason -</option>
                        @foreach(\App\Models\RefundRequest::REASONS as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Request Type --}}
                <div class="mb-3">
                    <label class="form-label">Request Type *</label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="request_type"
                               id="request_type_refund" value="refund" checked>
                        <label class="form-check-label" for="request_type_refund">
                            <i class="bi bi-cash me-1"></i>Refund to original payment method
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="request_type"
                               id="request_type_exchange" value="exchange">
                        <label class="form-check-label" for="request_type_exchange">
                            <i class="bi bi-arrow-left-right me-1"></i>Exchange for a different size/color
                        </label>
                    </div>
                </div>

                {{-- Exchange variant selector --}}
                <div id="exchange-section" class="mb-3" style="display:none">
                    <label class="form-label">Select replacement variant *</label>
                    <select name="exchange_variant_id" id="exchange-variant-select" class="form-select">
                        <option value="">- Select a variant -</option>
                    </select>
                    <div class="form-text">Only items with the same product are available for exchange.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Additional details</label>
                    <textarea name="details" rows="3" class="form-control"
                              placeholder="Please describe the issue…"></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Evidence photo <small class="text-muted">(optional - for damaged/wrong item)</small></label>
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
<script src="https://js.paystack.co/v1/inline.js"></script>
<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    // ── Pay Now panel ──────────────────────────────────────────────────────
    const payPanel = document.getElementById('pay-now-panel');
    if (payPanel) {
        const errDiv = document.getElementById('pay-now-error');
        const payBtn = document.getElementById('pn-pay-btn');
        const status = document.getElementById('pn-status');

        function showError(message) {
            errDiv.style.display = '';
            document.getElementById('pay-now-error-msg').textContent = message;
            if (payBtn) { payBtn.disabled = false; payBtn.innerHTML = '<i class="bi bi-credit-card me-1"></i>Pay Now'; }
        }

        if (payBtn) {
            payBtn.addEventListener('click', function() {
                payBtn.disabled = true;
                payBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Preparing payment...';
                if (status) status.textContent = '';

                fetch('{{ route("shop.paystack.initiate", $order) }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.success) {
                        showError(data.message || 'Could not prepare your payment.');
                        return;
                    }
                    if (typeof PaystackPop === 'undefined') {
                        showError('Payment window failed to load. Please refresh and try again.');
                        return;
                    }
                    const handler = PaystackPop.setup({
                        key: data.public_key,
                        email: data.email,
                        amount: data.amount_kobo,
                        ref: data.reference,
                        access_code: data.access_code,
                        metadata: { order_id: {{ $order->id }} },
                        callback: function(response) {
                            // Paystack completed the payment - let the server
                            // verify and confirm the order.
                            window.location.href = '{{ route("shop.paystack.callback", $order) }}?reference=' + encodeURIComponent(response.reference || '');
                        },
                        onClose: function() {
                            // Payment may have gone through even though the window
                            // was closed (e.g. page refreshed on Paystack's side).
                            // Re-check server-side so the page reflects reality.
                            payBtn.disabled = true;
                            payBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Confirming...';
                            if (status) status.textContent = 'Checking payment status...';

                            fetch('{{ route("shop.paystack.query", $order) }}', {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
                            })
                            .then(function(r) { return r.json(); })
                            .then(function(data) {
                                if (data.paid || data.payment_status === 'paid') {
                                    location.reload();
                                    return;
                                }
                                payBtn.disabled = false;
                                payBtn.innerHTML = '<i class="bi bi-credit-card me-1"></i>Pay Now';
                                if (status) status.textContent = 'Payment not completed. If you closed the window without paying, you can try again.';
                            })
                            .catch(function() {
                                payBtn.disabled = false;
                                payBtn.innerHTML = '<i class="bi bi-credit-card me-1"></i>Pay Now';
                                if (status) status.textContent = 'Could not confirm payment status. Please click Pay Now to retry.';
                            });
                        }
                    });
                    handler.openIframe();
                })
                .catch(function() {
                    showError('Network error. Please try again.');
                });
            });
        }
    }

    // ── Under Review auto-poll ───────────────────────────────────────────────
    var reviewEl = document.getElementById('pay-now-review');
    if (reviewEl) {
        var reviewOrderId = reviewEl.dataset.orderId;
        var csrfReview = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        var reviewPoll = setInterval(function() {
            fetch('{{ route("shop.paystack.query", $order) }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfReview, 'Accept': 'application/json' }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.paid || data.payment_status === 'paid') {
                    clearInterval(reviewPoll);
                    location.reload();
                } else if (data.payment_status !== 'under_review') {
                    clearInterval(reviewPoll);
                    location.reload();
                }
            })
            .catch(function() {});
        }, 30000);
    }

    // ── Refund form: toggle item fields on scope selection ────────────────────
    const itemVariants = @json($itemVariants);
    let selectedItemId = null;

    document.querySelectorAll('input[type="radio"][name="scope"]').forEach(radio => {
        radio.addEventListener('change', () => {
            const itemFields  = document.getElementById('item-fields');
            const itemIdInput = document.getElementById('selected-item-id');
            if (!itemFields) return;
            if (radio.value === 'item') {
                itemFields.style.display = '';
                if (itemIdInput && radio.dataset.itemId) {
                    itemIdInput.value = radio.dataset.itemId;
                    selectedItemId = radio.dataset.itemId;
                    const maxQty = radio.dataset.itemQty;
                    const qtyInput = document.querySelector('[name="quantity"]');
                    if (maxQty && qtyInput) {
                        qtyInput.max = maxQty;
                        if (parseInt(qtyInput.value) > parseInt(maxQty)) qtyInput.value = maxQty;
                    }
                    updateExchangeVariants(selectedItemId);
                }
            } else {
                itemFields.style.display = 'none';
                if (itemIdInput) itemIdInput.value = '';
                selectedItemId = null;
                updateExchangeVariants(null);
            }
        });
    });

    // ── Request type toggle ──────────────────────────────────────────────────
    document.querySelectorAll('input[type="radio"][name="request_type"]').forEach(radio => {
        radio.addEventListener('change', () => {
            const exchangeSection = document.getElementById('exchange-section');
            if (!exchangeSection) return;
            exchangeSection.style.display = radio.value === 'exchange' ? '' : 'none';
            if (radio.value === 'exchange' && selectedItemId) {
                updateExchangeVariants(selectedItemId);
            }
        });
    });

    function updateExchangeVariants(itemId) {
        const select = document.getElementById('exchange-variant-select');
        if (!select) return;
        select.innerHTML = '<option value="">- Select a variant -</option>';
        if (!itemId || !itemVariants[itemId]) return;
        itemVariants[itemId].forEach(v => {
            const opt = document.createElement('option');
            opt.value = v.id;
            opt.textContent = v.label + ' (' + v.stock + ' in stock)';
            select.appendChild(opt);
        });
    }
})();
</script>
@endpush

{{-- Change Payment Method Modal --}}
@if($order->payment_status !== 'paid')
@php $activePaymentMethods = \App\Models\PaymentMethod::where('is_active', true)->orderBy('key')->get(); @endphp
<div class="modal fade" id="changePaymentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="{{ route('shop.account.orders.change-payment-method', $order) }}">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Change Payment Method</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">Current method: <strong>{{ ucfirst(str_replace('_', ' ', $order->payment_method)) }}</strong></p>
                    @foreach($activePaymentMethods as $method)
                        @if($method->key !== $order->payment_method)
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="payment_method" id="pm_change_{{ $method->key }}" value="{{ $method->key }}" {{ $loop->first ? 'checked' : '' }}>
                            <label class="form-check-label" for="pm_change_{{ $method->key }}">
                                {{ $method->label }}
                            </label>
                        </div>
                        @endif
                    @endforeach
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Payment Method</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@push('scripts')
<script>
$(document).ready(function() {
    $('#order-items-table').DataTable({
        searching: true,
        paging: false,
        info: false,
        ordering: true,
        language: {
            search: '<i class="bi bi-search"></i>',
            searchPlaceholder: 'Search items...'
        },
        columnDefs: [
            { orderable: false, targets: [1] }
        ],
        order: [[0, 'asc']]
    });
});
</script>
@endpush

@endsection
