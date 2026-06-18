@extends('layouts.shop', ['title' => 'Verify Your Identity'])
@section('content')
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card border-0 shadow-sm"><div class="card-body p-4">
            <div class="text-center mb-3">
                <i class="bi bi-shield-lock fs-1 text-primary"></i>
                <h4 class="mt-2 mb-0">Two-Factor Verification</h4>
                <p class="text-muted small mt-1">A 6-digit code has been sent to your email address.</p>
            </div>

            @if(session('status'))
                <div class="alert alert-info py-2 small">{{ session('status') }}</div>
            @endif

            <form method="post" action="{{ route('shop.2fa.verify') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Verification Code</label>
                    <input type="text" name="code" class="form-control form-control-lg text-center font-monospace
                           @error('code') is-invalid @enderror"
                           inputmode="text" maxlength="20"
                           placeholder="000000 or backup code" autofocus autocomplete="one-time-code">
                    @error('code')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text small text-muted mt-1">
                        <i class="bi bi-key me-1"></i>No email access? Enter your backup code (e.g. <code>ABCD-12345</code>).
                    </div>
                </div>
                <button class="btn btn-primary w-100">Verify &amp; Sign In</button>
            </form>

            <div class="text-center mt-3 small text-muted">
                Didn't receive the code?
                <a href="{{ route('shop.login') }}">Go back and try again</a>
            </div>
        </div></div>
    </div>
</div>
@endsection
