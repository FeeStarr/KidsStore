@extends('layouts.shop', ['title' => 'Track Your Order'])

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 text-center">
                <div class="mb-3" style="font-size:2.5rem;">📦</div>
                <h3 class="fw-bold mb-2">Track Your Order</h3>
                <p class="text-muted mb-4">Enter your order reference and the email used at checkout.</p>

                <form action="{{ route('shop.order.lookup.submit') }}" method="post">
                    @csrf
                    <div class="mb-3 text-start">
                        <label class="form-label">Order Reference</label>
                        <input type="text" name="reference" class="form-control @error('reference') is-invalid @enderror"
                               placeholder="e.g. ORD-00001" value="{{ old('reference') }}" required>
                        @error('reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3 text-start">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                               placeholder="your@email.com" value="{{ old('email') }}" required>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <button class="btn btn-primary w-100" type="submit">
                        <i class="bi bi-search me-1"></i> Track Order
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
