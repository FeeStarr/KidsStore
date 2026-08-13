@extends('layouts.admin', ['title' => 'Edit Coupon'])
@section('content')
<div class="d-flex justify-content-between mb-3">
    <h3>Edit Coupon — {{ $coupon->code }}</h3>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.coupons.show', $coupon) }}" class="btn btn-outline-secondary">View</a>
        <a href="{{ route('admin.coupons.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>
</div>
@include('admin.coupons._form')
@endsection