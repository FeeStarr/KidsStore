@extends('layouts.delivery-portal', ['title' => 'Profile'])
@section('content')
<h5 class="mb-3">Profile</h5>

<div class="card mb-4">
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-4">Name</dt>
            <dd class="col-8">{{ $agent->name }}</dd>
            <dt class="col-4">Account #</dt>
            <dd class="col-8 font-monospace fw-bold">{{ $agent->account_number }}</dd>
            <dt class="col-4">Phone</dt>
            <dd class="col-8">{{ $agent->phone ?: '-' }}</dd>
            <dt class="col-4">Email</dt>
            <dd class="col-8">{{ $agent->email }}</dd>
            <dt class="col-4">Status</dt>
            <dd class="col-8">
                @if($agent->is_active)
                    <span class="badge bg-success">Active</span>
                @else
                    <span class="badge bg-secondary">Inactive</span>
                @endif
            </dd>
        </dl>
    </div>
</div>

<div class="card">
    <div class="card-header">Change Password</div>
    <div class="card-body">
        <form method="post" action="{{ route('delivery-portal.profile.password') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">Current Password</label>
                <input type="password" name="current_password" class="form-control @error('current_password') is-invalid @enderror" required>
                @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label">New Password</label>
                <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label">Confirm New Password</label>
                <input type="password" name="password_confirmation" class="form-control" required>
            </div>
            <button class="btn btn-primary">Update Password</button>
        </form>
    </div>
</div>
@endsection
