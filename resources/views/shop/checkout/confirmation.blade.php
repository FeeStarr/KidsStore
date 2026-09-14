@extends('layouts.shop', ['title' => $isExpired ? 'Order Expired' : 'Order Confirmed'])

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">

        @if(session('success'))
            <div class="alert alert-success text-center">{{ session('success') }}</div>
        @endif
        @if(session('info'))
            <div class="alert alert-info text-center">{{ session('info') }}</div>
        @endif

        {{-- EXPIRED STATE --}}
        @if($isExpired)
            <div class="text-center mb-4">
                <div class="mb-3">
                    <i class="bi bi-clock-history text-warning" style="font-size:3rem;"></i>
                </div>
                <h3 class="mb-2">This Order Has Expired</h3>
                <p class="text-muted">
                    The 24-hour payment window for order <strong class="text-dark">{{ $order->reference }}</strong> has ended.
                    The items in this order have been released back to inventory.
                </p>
            </div>

            <div class="text-center mb-4">
                <a href="{{ route('shop.home') }}" class="btn btn-primary px-4">
                    <i class="bi bi-bag me-1"></i>Shop Again
                </a>
            </div>

        {{-- CANCELLED STATE --}}
        @elseif($order->status === 'cancelled')
            <div class="text-center mb-4">
                <div class="mb-3">
                    <i class="bi bi-x-circle text-danger" style="font-size:3rem;"></i>
                </div>
                <h3 class="mb-2">Order Cancelled</h3>
                <p class="text-muted">
                    Order <strong class="text-dark">{{ $order->reference }}</strong> has been cancelled.
                </p>
            </div>

            <div class="text-center mb-4">
                <a href="{{ route('shop.home') }}" class="btn btn-primary px-4">
                    <i class="bi bi-bag me-1"></i>Shop Again
                </a>
            </div>

        {{-- PAID / CONFIRMED STATE --}}
        @elseif($isPaidOrConfirmed)
            <div class="text-center mb-4">
                <div class="mb-3">
                    <i class="bi bi-check-circle text-success" style="font-size:3rem;"></i>
                </div>
                <h3 class="mb-2">Payment Confirmed!</h3>
                <p class="text-muted">
                    Your order number is <strong class="text-dark">{{ $order->reference }}</strong>.
                    @if($order->payment_method === 'pay_on_delivery')
                        We'll review and confirm your order shortly.
                    @endif
                </p>
            </div>

            {{-- Basic order info --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-6 mb-3">
                            <strong>Order Number</strong><br>
                            {{ $order->reference }}
                        </div>
                        <div class="col-sm-6 mb-3">
                            <strong>Status</strong><br>
                            @php
                                $statusColors = [
                                    'pending confirmation' => 'warning',
                                    'confirmed' => 'info',
                                    'processing' => 'primary',
                                    'shipped' => 'primary',
                                    'shipping to station' => 'primary',
                                    'ready for pick up' => 'success',
                                    'delivered' => 'success',
                                ];
                                $color = $statusColors[$order->status] ?? 'secondary';
                            @endphp
                            <span class="badge bg-{{ $color }} text-capitalize">{{ $order->status }}</span>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <strong>Order Date</strong><br>
                            {{ $order->order_date?->format('M d, Y g:i A') ?? '-' }}
                        </div>
                        <div class="col-sm-6 mb-3">
                            <strong>Payment Method</strong><br>
                            {{ $order->payment_method === 'pay_now' ? 'Paystack (Pay Now)' : 'Pay on Delivery' }}
                        </div>
                        <div class="col-12 mb-3">
                            <strong>Total</strong><br>
                            &#8358;{{ number_format($order->grand_total, 2) }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center mb-4">
                <a href="{{ route('shop.order.track', $order->lookup_token) }}" class="btn btn-primary px-4 me-2">
                    <i class="bi bi-geo-alt me-1"></i>Track Your Order
                </a>
                <a href="{{ route('shop.home') }}" class="btn btn-outline-secondary px-4">
                    <i class="bi bi-bag me-1"></i>Continue Shopping
                </a>
            </div>

        {{-- PENDING PAYMENT - WITHIN 24H WINDOW --}}
        @else
            <div class="text-center mb-4">
                <div class="mb-3">
                    <i class="bi bi-hourglass-split text-warning" style="font-size:3rem;"></i>
                </div>
                <h3 class="mb-2">Order Placed!</h3>
                <p class="text-muted">
                    Your order number is <strong class="text-dark">{{ $order->reference }}</strong>.
                    Complete your payment within 24 hours to confirm your order.
                </p>
            </div>

            {{-- Basic order info --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-6 mb-3">
                            <strong>Order Number</strong><br>
                            {{ $order->reference }}
                        </div>
                        <div class="col-sm-6 mb-3">
                            <strong>Status</strong><br>
                            <span class="badge bg-warning text-capitalize">{{ $order->status }}</span>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <strong>Order Date</strong><br>
                            {{ $order->order_date?->format('M d, Y g:i A') ?? '-' }}
                        </div>
                        <div class="col-sm-6 mb-3">
                            <strong>Payment Method</strong><br>
                            {{ $order->payment_method === 'pay_now' ? 'Paystack (Pay Now)' : 'Pay on Delivery' }}
                        </div>
                        <div class="col-12 mb-3">
                            <strong>Total</strong><br>
                            &#8358;{{ number_format($order->grand_total, 2) }}
                        </div>
                    </div>
                </div>
            </div>

            {{-- Pay Now panel --}}
            @if($showPayNow)
                @if($order->payment_status === 'under_review')
                    <div class="card border-primary mb-4" id="pay-now-panel">
                        <div class="card-body text-center py-4">
                            <h5 class="mb-2"><i class="bi bi-hourglass-split me-2"></i>Payment Under Review</h5>
                            <p class="text-muted small mb-3">Your payment is being reviewed. We'll confirm shortly.</p>
                            <div class="spinner-border text-warning mb-2" role="status"></div>
                            <div id="pay-now-review" class="small text-muted" data-order-id="{{ $order->id }}">
                                Waiting for confirmation...
                            </div>
                        </div>
                    </div>
                @else
                    <div class="card border-primary mb-4" id="pay-now-panel">
                        <div class="card-body text-center py-4">
                            <h5 class="mb-2"><i class="bi bi-shield-lock me-2"></i>Complete Your Payment</h5>
                            <p class="text-muted small mb-3">Click Pay Now. A secure payment window will open where you can pay with your preferred method.</p>
                            <div class="mb-3">
                                <span class="fw-bold fs-5 text-primary">&#8358;{{ number_format($order->grand_total, 2) }}</span>
                            </div>
                            <button type="button" class="btn btn-primary btn-lg px-5" id="pn-pay-btn">
                                <i class="bi bi-credit-card me-1"></i>Pay Now
                            </button>
                            <div id="pn-status" class="small text-muted mt-3"></div>
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
            @endif

            <div class="text-center mb-4">
                <a href="{{ route('shop.home') }}" class="btn btn-outline-secondary px-4">
                    <i class="bi bi-bag me-1"></i>Continue Shopping
                </a>
            </div>
        @endif

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

@if($showPayNow && $order->payment_status !== 'under_review')
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
})();
</script>
@endif
@endsection
