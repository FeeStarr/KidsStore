@extends('layouts.admin', ['title' => 'Privacy Policy'])
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Privacy Policy</h3>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="card">
    <div class="card-body">
        <form method="post" action="{{ route('admin.privacy-policy.update') }}">
            @csrf @method('PUT')
            <div class="mb-3">
                <label class="form-label">Privacy Policy Content</label>
                <textarea name="privacy_policy" rows="20" class="form-control @error('privacy_policy') is-invalid @enderror"
                          placeholder="Enter your privacy policy here...">{{ old('privacy_policy', $policy) }}</textarea>
                @error('privacy_policy')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <button class="btn btn-primary">Save Changes</button>
        </form>
    </div>
</div>
@endsection
