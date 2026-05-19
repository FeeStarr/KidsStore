@extends('layouts.auth', ['title' => 'Admin Login'])

@section('content')
<div class="card auth-card">
    <div class="card-body p-5">
        <div class="text-center mb-5">
            <h2 class="mb-3"><i class="bi bi-balloon-heart-fill"></i> Kids Store</h2>
            <h4 class="mb-2">Admin Portal</h4>
            <p class="text-muted small">Sign in to access the admin panel</p>
        </div>

        <form method="post" action="{{ route('admin.login') }}">
            @csrf

            @if ($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="mb-3">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror"
                       value="{{ old('email') }}" required autofocus>
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <input type="password" name="password" id="password" class="form-control" required>
            </div>

            <div class="mb-4 form-check">
                <input type="checkbox" name="remember" id="remember" class="form-check-input">
                <label for="remember" class="form-check-label">Remember me</label>
            </div>

            <button type="submit" class="btn btn-primary w-100 btn-lg">
                <i class="bi bi-box-arrow-in-right"></i> Sign In
            </button>
        </form>

        <div class="text-center mt-4">
            <a href="{{ route('shop.home') }}" class="text-muted small">Back to Store</a>
        </div>
    </div>
</div>
@endsection