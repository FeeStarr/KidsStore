@extends('layouts.shop')

@section('content')
<div class="row justify-content-center py-4">
    <div class="col-12 col-md-8 col-lg-6 col-xl-5">
        <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-4">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary mb-3" style="width: 64px; height: 64px;">
                        <i class="bi bi-shield-lock fs-3"></i>
                    </div>
                    <h2 class="h3 fw-bold mb-1">Reset Password</h2>
                    <p class="text-muted mb-0">Create a new secure password for your account.</p>
                </div>

                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">
                        <ul class="mb-0 ps-3">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('shop.password.update') }}">
                    @csrf

                    <input type="hidden" name="token" value="{{ $token }}">

                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold">Email Address</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="{{ $email }}"
                            required
                            readonly
                            class="form-control form-control-lg bg-light"
                        />
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label fw-semibold">New Password</label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            required
                            autofocus
                            class="form-control form-control-lg"
                        />
                        @error('password')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Minimum 8 characters with uppercase, lowercase, number, and special character.</div>
                    </div>

                    <div class="mb-4">
                        <label for="password_confirmation" class="form-label fw-semibold">Confirm Password</label>
                        <input
                            type="password"
                            id="password_confirmation"
                            name="password_confirmation"
                            required
                            class="form-control form-control-lg"
                        />
                        @error('password_confirmation')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100">
                        <i class="bi bi-arrow-repeat me-1"></i> Reset Password
                    </button>
                </form>

                <div class="mt-4 text-center">
                    <a href="{{ route('shop.login') }}" class="text-decoration-none fw-semibold">Back to login</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
