@extends('layouts.shop', ['title' => 'Order ' . $order->reference])

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="mb-0">Order {{ $order->reference }}</h3>
            <a href="{{ route('shop.order.lookup') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Track another order
            </a>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('info'))
            <div class="alert alert-info">{{ session('info') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @if($order->payment_status !== 'paid' && in_array($order->status, ['pending payment', 'confirmed']) && !in_array($order->status, ['cancelled', 'expired']))
        <div class="card border-primary mb-4" id="pay-now-panel">
            <div class="card-body text-center py-4">
                @if($order->payment_status === 'under_review')
                    <h5 class="mb-2"><i class="bi bi-hourglass-split me-2"></i>Payment Under Review</h5>
                    <p class="text-muted small mb-3">Your payment is being reviewed. We'll confirm shortly.</p>
                    <div class="spinner-border text-warning mb-2" role="status"></div>
                    <div id="pay-now-review" class="small text-muted" data-order-id="{{ $order->id }}">
                        Waiting for confirmation...
                    </div>
                @elseif(session('verifying_payment'))
                    <h5 class="mb-2"><i class="bi bi-hourglass-split me-2"></i>Verifying Your Payment</h5>
                    <p class="text-muted small mb-3">We received your payment and are confirming it with Paystack. This usually takes a few seconds.</p>
                    <div class="spinner-border text-primary mb-2" role="status"></div>
                    <div id="pn-verifying" class="small text-muted" data-order-id="{{ $order->id }}">
                        Checking payment status...
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
            <div id="pay-now-error" style="display:none" class="text-danger small p-3 pt-0 text-center">
                <div id="pay-now-error-msg"></div>
                <div class="mt-3">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="location.reload()">
                        <i class="bi bi-arrow-clockwise me-1"></i>Retry
                    </button>
                </div>
            </div>
        </div>
        @endif

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-sm-6 mb-3">
                        <strong>Status</strong><br>
                        @php
                            $statusColors = [
                                'pending' => 'warning',
                                'confirmed' => 'info',
                                'processing' => 'primary',
                                'shipped' => 'primary',
                                'shipping to station' => 'primary',
                                'ready for pick up' => 'success',
                                'delivered' => 'success',
                                'cancelled' => 'danger',
                            ];
                            $color = $statusColors[$order->status] ?? 'secondary';
                        @endphp
                        <span class="badge bg-{{ $color }} fs-6 text-capitalize">{{ $order->status }}</span>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <strong>Payment Status</strong><br>
                        <span class="text-capitalize">{{ $order->payment_status }}</span>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <strong>Order Date</strong><br>
                        {{ $order->order_date?->format('M d, Y g:i A') ?? '-' }}
                    </div>
                    <div class="col-sm-6 mb-3">
                        <strong>Delivery Method</strong><br>
                        {{ ucfirst($order->delivery_method) }}
                        @if($order->pickupStation)
                            <small class="text-muted d-block">{{ $order->pickupStation->name }}</small>
                        @endif
                    </div>
                    @if($order->delivery_address)
                        <div class="col-12 mb-3">
                            <strong>Delivery Address</strong><br>
                            {{ $order->delivery_address }}
                        </div>
                    @endif
                    @if($order->guest_name || $order->guest_email)
                        <div class="col-sm-6 mb-3">
                            <strong>Name</strong><br>
                            {{ $order->guest_name ?? $order->customer?->name ?? '-' }}
                        </div>
                        <div class="col-sm-6 mb-3">
                            <strong>Email</strong><br>
                            {{ $order->guest_email ?? $order->customer?->email ?? '-' }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white"><strong>Order Items</strong></div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <tbody>
                        @foreach($order->items as $item)
                            <tr>
                                <td>
                                    {{ $item->product->name }}
                                    @if($item->variant)
                                        <small class="text-muted d-block">{{ $item->variant->options_label }}</small>
                                    @endif
                                </td>
                                <td class="text-center">x{{ $item->quantity }}</td>
                                <td class="text-end">&#8358;{{ number_format($item->line_total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr><td colspan="2" class="text-end fw-bold">Subtotal</td><td class="text-end">&#8358;{{ number_format($order->subtotal, 2) }}</td></tr>
                        @if($order->discount > 0)
                            <tr><td colspan="2" class="text-end text-success">Discount</td><td class="text-end text-success">-&#8358;{{ number_format($order->discount, 2) }}</td></tr>
                        @endif
                        @if($order->shipping_fee > 0)
                            <tr><td colspan="2" class="text-end">Shipping</td><td class="text-end">&#8358;{{ number_format($order->shipping_fee, 2) }}</td></tr>
                        @endif
                        <tr><td colspan="2" class="text-end fw-bold">Total</td><td class="text-end fw-bold">&#8358;{{ number_format($order->grand_total, 2) }}</td></tr>
                    </tfoot>
                </table>
            </div>
        </div>

        @if(!Auth::check())
            <div class="card border-0 shadow-sm bg-light">
                <div class="card-body text-center">
                    <p class="mb-2 fw-semibold">Want to track all your orders in one place?</p>
                    <a href="{{ route('shop.register') }}" class="btn btn-primary btn-sm" style="border-radius:50px;">Create an Account</a>
                    <span class="text-muted mx-2">or</span>
                    <a href="{{ route('shop.login') }}" class="btn btn-outline-secondary btn-sm" style="border-radius:50px;">Log In</a>
                </div>
            </div>
        @endif
    </div>
</div>

<script src="https://js.paystack.co/v1/inline.js"></script>
<script>
(function () {
    var csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    var payPanel = document.getElementById('pay-now-panel');
    if (!payPanel) return;

    var errDiv = document.getElementById('pay-now-error');
    var payBtn = document.getElementById('pn-pay-btn');
    var status = document.getElementById('pn-status');

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

            fetch('{{ route("shop.paystack.guest-initiate", $order->lookup_token) }}', {
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
                var handler = PaystackPop.setup({
                    key: data.public_key,
                    email: data.email,
                    amount: data.amount_kobo,
                    ref: data.reference,
                    access_code: data.access_code,
                    metadata: { order_id: {{ $order->id }} },
                    callback: function(response) {
                        window.location.href = '{{ route("shop.paystack.guest-callback", $order->lookup_token) }}?reference=' + encodeURIComponent(response.reference || '');
                    },
                    onClose: function() {
                        payBtn.disabled = true;
                        payBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Confirming...';
                        if (status) status.textContent = 'Checking payment status...';

                        fetch('{{ route("shop.paystack.guest-query", $order->lookup_token) }}', {
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
                            if (status) status.textContent = 'Payment not completed. You can try again.';
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

    var reviewEl = document.getElementById('pay-now-review');
    if (reviewEl) {
        var reviewPoll = setInterval(function() {
            fetch('{{ route("shop.paystack.guest-query", $order->lookup_token) }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
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
            });
        }, 10000);
    }

    var verifyEl = document.getElementById('pn-verifying');
    if (verifyEl) {
        var verifyPoll = setInterval(function() {
            fetch('{{ route("shop.paystack.guest-query", $order->lookup_token) }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.paid || data.payment_status === 'paid') {
                    clearInterval(verifyPoll);
                    location.reload();
                } else if (data.payment_status === 'under_review') {
                    clearInterval(verifyPoll);
                    location.reload();
                } else if (data.seconds_remaining !== undefined && data.seconds_remaining <= 0) {
                    clearInterval(verifyPoll);
                    verifyEl.innerHTML = 'Verification is taking longer than expected. <button class="btn btn-sm btn-outline-primary ms-2" onclick="location.reload()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>';
                }
            });
        }, 5000);
    }
})();
</script>
@endsection
