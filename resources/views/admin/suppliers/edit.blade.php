@extends('layouts.admin', ['title' => 'Edit Supplier'])
@section('content')
<h3 class="mb-3">Edit Supplier</h3>

<form method="post" action="{{ route('admin.suppliers.update', $supplier) }}">
    @csrf
    @method('PUT')
    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <label class="form-label">Name *</label>
            <input name="name" class="form-control" value="{{ old('name', $supplier->name) }}" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Contact Name</label>
            <input name="contact_name" class="form-control" value="{{ old('contact_name', $supplier->contact_name) }}">
        </div>
        <div class="col-md-4">
            <label class="form-label">Phone</label>
            <input name="phone" class="form-control" value="{{ old('phone', $supplier->phone) }}">
        </div>
        <div class="col-md-4">
            <label class="form-label">Email</label>
            <input name="email" class="form-control" type="email" value="{{ old('email', $supplier->email) }}">
        </div>
        <div class="col-md-4">
            <label class="form-label">Active</label>
            <select name="is_active" class="form-select">
                <option value="1" {{ $supplier->is_active ? 'selected' : '' }}>Yes</option>
                <option value="0" {{ ! $supplier->is_active ? 'selected' : '' }}>No</option>
            </select>
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label">Address</label>
        <textarea name="address" class="form-control" rows="3">{{ old('address', $supplier->address) }}</textarea>
    </div>

    <button class="btn btn-primary" type="submit">Save</button>
    <a href="{{ route('admin.suppliers.index') }}" class="btn btn-link">Cancel</a>
</form>

@endsection
