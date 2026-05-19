@extends('layouts.shop', ['title' => 'Login'])
@section('content')
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card border-0 shadow-sm"><div class="card-body p-4">
            <h4 class="mb-3">Welcome back</h4>
            <form method="post" action="{{ route('shop.login') }}">
                @csrf
                <div class="mb-3"><label class="form-label">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" class="form-control" required autofocus></div>
                <div class="mb-3"><label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required></div>
                <div class="form-check mb-3">
                    <input type="checkbox" name="remember" id="remember" class="form-check-input">
                    <label for="remember" class="form-check-label">Remember me</label>
                </div>
                <button class="btn btn-primary w-100">Log in</button>
            </form>
            <div class="text-center mt-3 small">
                Don't have an account? <a href="{{ route('shop.register') }}">Sign up</a>
            </div>
        </div></div>
    </div>
</div>
@endsection
