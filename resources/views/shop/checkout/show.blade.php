@extends('layouts.shop', ['title' => 'Checkout'])
@section('content')

<h3 class="mb-3">Checkout</h3>

<form action="{{ route('shop.checkout.place') }}" method="post" class="row g-4">
    @csrf

    <div class="col-md-7">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <h5 class="mb-3">Shipping details</h5>

            <div class="mb-3">
                <label class="form-label">Name</label>
                <input type="text" class="form-control" value="{{ $customer->name }}" disabled>
            </div>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" class="form-control" value="{{ $customer->email }}" disabled>
            </div>
            <div class="mb-3">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control" value="{{ old('phone', $customer->phone) }}" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Address</label>
                <textarea name="address" rows="3" class="form-control" required>{{ old('address', $customer->address) }}</textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Delivery Method</label>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="delivery_method" id="delivery_method_delivery" value="delivery"
                        {{ old('delivery_method', 'delivery') === 'delivery' ? 'checked' : '' }}>
                    <label class="form-check-label" for="delivery_method_delivery">
                        Home Delivery
                        <small class="text-muted d-block">We will deliver to your address</small>
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="delivery_method" id="delivery_method_pickup" value="pickup"
                        {{ old('delivery_method') === 'pickup' ? 'checked' : '' }}>
                    <label class="form-check-label" for="delivery_method_pickup">
                        Pick Up
                        <small class="text-muted d-block">Pick up from our store</small>
                    </label>
                </div>
                @error('delivery_method')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>
        </div></div>
    </div>

    <div class="col-md-5">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <h5 class="mb-3">Order summary</h5>
            <ul class="list-unstyled mb-3">
                @foreach($items as $line)
                    <li class="d-flex justify-content-between mb-2">
                        <span>
                            {{ $line->product->name }}
                            @if($line->variant->options_label)
                                <small class="text-muted">— {{ $line->variant->options_label }}</small>
                            @endif
                            <small class="text-muted">x{{ $line->quantity }}</small>
                        </span>
                        <strong>&#8358;{{ number_format($line->line_total, 2) }}</strong>
                    </li>
                @endforeach
            </ul>
            <dl class="row mb-3">
                <dt class="col-6">Subtotal</dt><dd class="col-6 text-end">&#8358;{{ number_format($subtotal, 2) }}</dd>
                <dt class="col-6">Shipping fee</dt>
                <dd class="col-6 text-end">
                    <input type="number" step="0.01" min="0" name="shipping_fee" value="0" class="form-control form-control-sm text-end" style="max-width:120px;display:inline-block">
                </dd>
            </dl>
            <button class="btn btn-primary w-100">Place order</button>
            <div class="small text-muted mt-2">Pay on delivery or after confirmation by our team.</div>
        </div></div>
    </div>
</form>

@endsection
