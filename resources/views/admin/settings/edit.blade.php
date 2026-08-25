@extends('layouts.admin', ['title' => 'Settings'])
@section('content')

<h3 class="mb-3">Settings</h3>

<form action="{{ route('admin.settings.update') }}" method="post" class="card p-3">
    @csrf
    @method('put')

    <h5 class="mb-3">General</h5>

    <div class="mb-3 row">
        <label class="col-sm-4 col-form-label">App Name</label>
        <div class="col-sm-4">
            <input type="text" name="app_name" class="form-control" value="{{ old('app_name', $appName) }}">
            <small class="text-muted">Displayed in navigation, titles, and emails</small>
        </div>
    </div>

    <h5 class="mb-3 mt-4">Shipping</h5>

    <div class="mb-3 row">
        <label class="col-sm-4 col-form-label">Home Delivery Fee (₦)</label>
        <div class="col-sm-4">
            <input type="number" step="0.01" min="0" name="home_delivery_fee_disabled" class="form-control" value="{{ old('home_delivery_fee', $homeFee) }}" disabled>
            <small class="text-muted">Coming soon - not yet active</small>
        </div>
    </div>

    <div class="mb-3 row">
        <label class="col-sm-4 col-form-label">Shipping Fee per order (₦)</label>
        <div class="col-sm-4">
            <input type="number" step="0.01" min="0" name="shipping_fee" class="form-control" value="{{ old('shipping_fee', $shippingFee) }}">
            <small class="text-muted">Flat fee charged once per order</small>
        </div>
    </div>

    <div class="mb-3 row">
        <label class="col-sm-4 col-form-label">Shipping Discount (%)</label>
        <div class="col-sm-4">
            <input type="number" step="0.01" min="0" max="100" name="shipping_discount" class="form-control" value="{{ old('shipping_discount', $shippingDiscount) }}">
            <small class="text-muted">Percentage discount applied to shipping (0–100)</small>
        </div>
    </div>

    <h5 class="mb-3 mt-4">Pickup Station Commission</h5>

    <div class="mb-3 row">
        <label class="col-sm-4 col-form-label">Commission Rate (%)</label>
        <div class="col-sm-4">
            <input type="number" step="0.01" min="0" max="100" name="commission_rate" class="form-control" value="{{ old('commission_rate', $commissionRate) }}">
            <small class="text-muted">Percentage of line total earned per item</small>
        </div>
    </div>

    <div class="mb-3 row">
        <label class="col-sm-4 col-form-label">Minimum Commission per Order (₦)</label>
        <div class="col-sm-4">
            <input type="number" step="1" min="0" name="commission_min" class="form-control" value="{{ old('commission_min', $commissionMin) }}">
            <small class="text-muted">Minimum commission a station earns per order</small>
        </div>
    </div>

    <div class="mb-3 row">
        <label class="col-sm-4 col-form-label">Maximum Commission per Order (₦)</label>
        <div class="col-sm-4">
            <input type="number" step="1" min="0" name="commission_max" class="form-control" value="{{ old('commission_max', $commissionMax) }}">
            <small class="text-muted">Maximum commission a station earns per order</small>
        </div>
    </div>

    <div class="mt-3">
        <button class="btn btn-primary">Save</button>
    </div>
</form>

@endsection
