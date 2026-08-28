@extends('layouts.shop', ['title' => 'Verify Your Email'])
@section('content')

<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5 px-4">
                <div class="mb-3">
                    <i class="bi bi-envelope-check text-primary" style="font-size:3rem"></i>
                </div>
                <h4 class="mb-2">Verify Your Email</h4>
                <p class="text-muted small mb-4">Enter your email address to receive a 6-digit verification code to continue with your order.</p>

                <form method="POST" action="{{ route('shop.checkout.send-otp') }}">
                    @csrf
                    <div class="mb-3">
                        <input type="email" name="email" class="form-control form-control-lg text-center"
                               placeholder="you@example.com" value="{{ old('email') }}" required autofocus>
                        @error('email')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100">
                        <i class="bi bi-send me-1"></i> Send Verification Code
                    </button>
                </form>

                <div class="mt-4">
                    <a href="{{ route('shop.cart.index') }}" class="text-muted small">
                        <i class="bi bi-arrow-left me-1"></i>Back to cart
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
