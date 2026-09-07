@extends('layouts.admin', ['title' => 'Edit Delivery Charge'])
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Edit Delivery Charge</h3>
    <a href="{{ route('admin.delivery-charges.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
</div>

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="card" style="max-width:600px">
    <div class="card-body">
        <form action="{{ route('admin.delivery-charges.update', $deliveryCharge) }}" method="post">
            @csrf @method('PUT')
            @include('admin.delivery_charges._form')
            <button class="btn btn-primary mt-3">Update Charge</button>
        </form>
    </div>
</div>
@endsection
