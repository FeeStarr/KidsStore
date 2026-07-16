@extends('layouts.admin', ['title' => 'Settings'])
@section('content')

<h3 class="mb-3">Settings</h3>

<form action="{{ route('admin.settings.update') }}" method="post" class="card p-3">
    @csrf
    @method('put')

    <h5 class="mb-3">Shipping</h5>

    <div class="mb-3 row">
        <label class="col-sm-4 col-form-label">Home Delivery Fee (₦)</label>
        <div class="col-sm-4">
            <input type="number" step="0.01" min="0" name="home_delivery_fee_disabled" class="form-control" value="{{ old('home_delivery_fee', $homeFee) }}" disabled>
            <small class="text-muted">Coming soon — not yet active</small>
        </div>
    </div>

    <div class="mb-3 row">
        <label class="col-sm-4 col-form-label">Pickup Shipping Fee per item (₦)</label>
        <div class="col-sm-4">
            <input type="number" step="0.01" min="0" name="shipping_fee" class="form-control" value="{{ old('shipping_fee', $shippingFee) }}">
            <small class="text-muted">Flat fee charged per item for pickup orders</small>
        </div>
    </div>

    <div class="mb-3 row">
        <label class="col-sm-4 col-form-label">Shipping Discount (%)</label>
        <div class="col-sm-4">
            <input type="number" step="0.01" min="0" max="100" name="shipping_discount" class="form-control" value="{{ old('shipping_discount', $shippingDiscount) }}">
            <small class="text-muted">Percentage discount applied to shipping (0–100)</small>
        </div>
    </div>

    <div class="mt-3">
        <button class="btn btn-primary">Save</button>
    </div>
</form>

@endsection
