@extends('layouts.shop', ['title' => 'Sign up'])
@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm"><div class="card-body p-4">
            <h4 class="mb-3">Create your account</h4>
            <form method="post" action="{{ route('shop.register') }}">
                @csrf
                <div class="mb-3"><label class="form-label">Full name</label>
                    <input type="text" name="name" value="{{ old('name') }}" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Phone</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" class="form-control"></div>
                <div class="mb-3"><label class="form-label">Address</label>
                    <textarea name="address" rows="2" class="form-control">{{ old('address') }}</textarea></div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Confirm password</label>
                        <input type="password" name="password_confirmation" class="form-control" required></div>
                </div>
                <button class="btn btn-primary w-100">Create account</button>
            </form>
            <div class="text-center mt-3 small">
                Already have an account? <a href="{{ route('shop.login') }}">Log in</a>
            </div>
        </div></div>
    </div>
</div>
@endsection
