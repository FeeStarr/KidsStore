@extends('layouts.delivery-portal', ['title' => 'Login'])
@section('content')
<div class="row justify-content-center mt-5">
    <div class="col-sm-10 col-md-6 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h4 class="text-center mb-4"><i class="bi bi-truck me-2"></i>Delivery Agent Login</h4>

                <form method="post" action="{{ route('delivery-portal.login.post') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                               value="{{ old('email') }}" required autofocus>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror"
                               required>
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <button class="btn btn-primary w-100 btn-action">Login</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
