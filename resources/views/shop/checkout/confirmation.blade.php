@extends('layouts.shop', ['title' => 'Order Confirmed'])

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">

        {{-- Success banner --}}
        <div class="text-center mb-4">
            <div class="mb-3">
                <i class="bi bi-check-circle text-success" style="font-size:3rem;"></i>
            </div>
            <h3 class="mb-2">Order Confirmed!</h3>
            <p class="text-muted">
                Your order number is <strong class="text-dark">{{ $order->reference }}</strong>.
                @if($order->payment_method === 'pay_on_delivery')
                    We'll review and confirm your order shortly.
                @endif
            </p>
        </div>

        @if(session('success'))
            <div class="alert alert-success text-center">{{ session('success') }}</div>
        @endif
        @if(session('info'))
            <div class="alert alert-info text-center">{{ session('info') }}</div>
        @endif

        {{-- Pay Now panel (if applicable) --}}
        @if($showPayNow && $order->payment_status !== 'paid' && $order->payment_status !== 'refunded' && in_array($order->status, ['pending payment', 'confirmed']) && !in_array($order->status, ['cancelled', 'expired']))
        <div class="card border-primary mb-4" id="pay-now-panel">
            <div class="card-body text-center py-4">
                @if($order->payment_status === 'under_review')
                    <h5 class="mb-2"><i class="bi bi-hourglass-split me-2"></i>Payment Under Review</h5>
                    <p class="text-muted small mb-3">Your payment is being reviewed. We'll confirm shortly.</p>
                    <div class="spinner-border text-warning mb-2" role="status"></div>
                    <div id="pay-now-review" class="small text-muted" data-order-id="{{ $order->id }}">
                        Waiting for confirmation...
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

        {{-- Order summary --}}
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
                                'pending payment' => 'warning',
                                'pending confirmation' => 'warning',
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

        {{-- Order items --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white"><strong>Order Items</strong></div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <tbody>
                        @foreach($order->items as $item)
                            <tr>
                                <td>
                                    {{ $item->product->name ?? 'Product' }}
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

        {{-- Action buttons --}}
        <div class="text-center mb-4">
            <a href="{{ route('shop.order.track', $order->lookup_token) }}" class="btn btn-primary px-4 me-2">
                <i class="bi bi-geo-alt me-1"></i>Track Your Order
            </a>
            <a href="{{ route('shop.home') }}" class="btn btn-outline-secondary px-4">
                <i class="bi bi-bag me-1"></i>Continue Shopping
            </a>
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

@if($showPayNow && $order->payment_status !== 'paid' && $order->payment_status !== 'refunded' && in_array($order->status, ['pending payment', 'confirmed']))
<script src="https://js.paystack.co/v1/inline.js"></script>
<script>
(function(){
    var btn = document.getElementById('pn-pay-btn');
    if (!btn) return;
    btn.addEventListener('click', function(){
        var handler = PaystackPop.setup({
            key: '{{ config("paystack.public_key") }}',
            email: '{{ $order->guest_email ?? $order->customer?->email ?? "" }}',
            amount: {{ (int) ($order->grand_total * 100) }},
            currency: '{{ config("paystack.currency", "NGN") }}',
            ref: '{{ $order->reference }}-{{ time() }}',
            onClose: function(){
                document.getElementById('pn-status').innerHTML = '<span class="text-warning">Payment window closed. <a href="javascript:location.reload()">Click here to retry</a></span>';
            },
            callback: function(response){
                document.getElementById('pn-status').innerHTML = '<div class="spinner-border text-primary" role="status"></div><br>Verifying payment...';
                btn.style.display = 'none';
                fetch('/checkout/verify-payment', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        reference: response.reference,
                        order_id: {{ $order->id }}
                    })
                })
                .then(function(r){ return r.json(); })
                .then(function(data){
                    if (data.success) {
                        document.getElementById('pn-status').innerHTML = '<div class="text-success fw-bold"><i class="bi bi-check-circle me-1"></i>Payment successful! Refreshing...</div>';
                        setTimeout(function(){ location.reload(); }, 1500);
                    } else {
                        document.getElementById('pn-status').innerHTML = '<div class="text-danger">' + (data.message || 'Payment verification failed.') + '</div>';
                        btn.style.display = '';
                    }
                })
                .catch(function(){
                    document.getElementById('pn-status').innerHTML = '<div class="text-danger">Could not verify payment. <a href="javascript:location.reload()">Retry</a></div>';
                    btn.style.display = '';
                });
            }
        });
        handler.openIframe();
    });
})();
</script>
@endif
@endsection
