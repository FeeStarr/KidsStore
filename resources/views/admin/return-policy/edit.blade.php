@extends('layouts.admin', ['title' => 'Return Policy'])
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Return Policy</h3>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="card">
    <div class="card-body">
        <form method="post" action="{{ route('admin.return-policy.update') }}">
            @csrf @method('PUT')
            <div class="mb-3">
                <label class="form-label">Return Policy Content</label>
                <textarea name="return_policy" rows="20" class="form-control @error('return_policy') is-invalid @enderror"
                          placeholder="Enter your return policy here...">{{ old('return_policy', $policy) }}</textarea>
                @error('return_policy')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <button class="btn btn-primary">Save Changes</button>
        </form>
    </div>
</div>
@endsection
