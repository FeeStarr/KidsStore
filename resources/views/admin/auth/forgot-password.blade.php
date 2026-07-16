@extends('layouts.admin')

@section('content')
<div class="row justify-content-center py-4">
    <div class="col-12 col-md-8 col-lg-6 col-xl-5">
        <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-4">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary mb-3" style="width: 64px; height: 64px;">
                        <i class="bi bi-envelope-paper fs-3"></i>
                    </div>
                    <h2 class="h3 fw-bold mb-1">Admin Forgot Password</h2>
                    <p class="text-muted mb-0">Request a secure reset link for your admin account.</p>
                </div>

                @if (session('status'))
                    <div class="alert alert-success" role="alert">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">
                        <ul class="mb-0 ps-3">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <p class="text-muted mb-4">
                    Enter your admin email address and we will send you a password reset link.
                </p>

                <form method="POST" action="{{ route('admin.password.email') }}">
                    @csrf

                    <div class="mb-4">
                        <label for="email" class="form-label fw-semibold">Email Address</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="{{ old('email') }}"
                            required
                            autofocus
                            class="form-control form-control-lg"
                        />
                        @error('email')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100">
                        <i class="bi bi-send me-1"></i> Send Reset Link
                    </button>
                </form>

                <div class="mt-4 text-center">
                    <a href="{{ route('admin.login') }}" class="text-decoration-none fw-semibold">Back to login</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
