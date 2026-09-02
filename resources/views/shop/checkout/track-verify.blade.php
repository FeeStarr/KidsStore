@extends('layouts.shop', ['title' => 'Verify to Track Order'])
@section('content')
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5 px-4">
                <div class="mb-3">
                    <i class="bi bi-shield-lock text-primary" style="font-size:3rem"></i>
                </div>
                <h4 class="mb-2">Verify to view order</h4>
                <p class="text-muted small mb-1">We've sent a 6-digit code to</p>
                <p class="fw-bold mb-1">{{ $email }}</p>
                <p class="text-muted small mb-3">Order {{ $order->reference }} - code expires in 10 minutes.</p>

                <form method="POST" action="{{ route('shop.order.track.verify.post', $token) }}">
                    @csrf
                    <input type="hidden" name="email" value="{{ $email }}">
                    <div class="mb-3">
                        <input type="text" name="code" class="form-control form-control-lg text-center fw-bold"
                               placeholder="000000" maxlength="6" pattern="[0-9]{6}" inputmode="numeric"
                               autocomplete="one-time-code" autofocus required
                               style="letter-spacing: .5rem; font-size: 1.5rem;">
                        @error('code')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100">
                        <i class="bi bi-check-circle me-1"></i> Verify & View Order
                    </button>
                </form>

                <div class="mt-3">
                    <form method="POST" action="{{ route('shop.order.track.verify.resend', $token) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-link text-muted small p-0">
                            Didn't receive the code? Resend
                        </button>
                    </form>
                </div>
                <div class="mt-2">
                    <a href="{{ route('shop.order.lookup') }}" class="text-muted small">
                        <i class="bi bi-arrow-left me-1"></i>Back to lookup
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
