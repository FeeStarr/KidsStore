@extends('layouts.admin', ['title' => 'New Coupon'])
@section('content')
<div class="d-flex justify-content-between mb-3">
    <h3>New Coupon</h3>
    <a href="{{ route('admin.coupons.index') }}" class="btn btn-outline-secondary">Back</a>
</div>
@include('admin.coupons._form')
@endsection