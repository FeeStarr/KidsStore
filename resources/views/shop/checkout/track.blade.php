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
@endsection
