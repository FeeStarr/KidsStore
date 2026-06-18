@extends('layouts.auth', ['title' => 'Admin 2FA'])

@section('content')
<div class="card auth-card">
    <div class="card-body p-5">
        <div class="text-center mb-5">
            <h2 class="mb-3"><i class="bi bi-balloon-heart-fill"></i> Kids Store</h2>
            <h4 class="mb-2">Two-Factor Authentication</h4>
            <p class="text-muted small">Enter the 6-digit code sent to your email.</p>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <form method="post" action="{{ route('admin.login.verify-2fa') }}">
            @csrf

            <div class="mb-3">
                <label for="code" class="form-label">Verification Code</label>
                <input type="text" name="code" id="code"
                       class="form-control form-control-lg text-center font-monospace @error('code') is-invalid @enderror"
                       value="{{ old('code') }}"
                       inputmode="text" placeholder="000000 or backup code"
                       required autofocus maxlength="20">
                @error('code')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="form-text text-muted small mt-1">
                    <i class="bi bi-key me-1"></i>Can't access your email? Enter your backup code (e.g. <code>ABCD-12345</code>).
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 btn-lg">
                Verify
            </button>
        </form>

        <div class="text-center mt-4">
            <a href="{{ route('admin.login') }}" class="text-muted small">Back to login</a>
        </div>
    </div>
</div>
@endsection
