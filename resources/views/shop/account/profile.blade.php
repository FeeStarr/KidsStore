@extends('layouts.shop', ['title' => 'My Profile'])
@section('content')

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">My Profile</h3>
    <a href="{{ route('shop.account.orders.index') }}" class="btn btn-outline-secondary btn-sm">My Orders</a>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <h5 class="mb-3">Account Details</h5>
                <form method="post" action="{{ route('shop.account.profile.update') }}">
                    @csrf
                    @method('put')
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" value="{{ $user->email }}" disabled>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" value="{{ old('phone', $user->phone) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Primary Address</label>
                            <input type="text" name="address" class="form-control" value="{{ old('address', $user->address) }}">
                        </div>
                    </div>
                    <div class="row g-2 mt-1">
                        <div class="col-md-6">
                            <label class="form-label">First Name</label>
                            <input type="text" name="first_name" class="form-control" value="{{ old('first_name', optional($user->profile)->first_name) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="last_name" class="form-control" value="{{ old('last_name', optional($user->profile)->last_name) }}">
                        </div>
                    </div>
                    <div class="mb-3 mt-2">
                        <label class="form-label">Bio</label>
                        <textarea name="bio" rows="3" class="form-control">{{ old('bio', optional($user->profile)->bio) }}</textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Avatar URL</label>
                        <input type="url" name="avatar_url" class="form-control" value="{{ old('avatar_url', optional($user->profile)->avatar_url) }}">
                    </div>
                    <button class="btn btn-primary">Save Profile</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <h5 class="mb-3">Saved Addresses</h5>
                @forelse($user->addresses as $address)
                    <div class="border rounded p-3 mb-3">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="fw-semibold">{{ $address->label ?: 'Address' }}</div>
                                <div>{{ $address->line1 }}</div>
                                @if($address->line2)<div>{{ $address->line2 }}</div>@endif
                                <div>{{ $address->city }}{{ $address->state ? ', '.$address->state : '' }}</div>
                                <div>{{ $address->country }}{{ $address->postal_code ? ' • '.$address->postal_code : '' }}</div>
                                @if($address->is_default)
                                    <span class="badge bg-success mt-2">Default</span>
                                @endif
                            </div>
                            <div class="d-flex flex-column gap-2">
                                <form method="post" action="{{ route('shop.account.addresses.default', $address) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-primary">Make Default</button>
                                </form>
                                <form method="post" action="{{ route('shop.account.addresses.destroy', $address) }}">
                                    @csrf
                                    @method('delete')
                                    <button class="btn btn-sm btn-outline-danger">Remove</button>
                                </form>
                            </div>
                        </div>
                        <form method="post" action="{{ route('shop.account.addresses.update', $address) }}" class="mt-3">
                            @csrf
                            @method('put')
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <input type="text" name="label" class="form-control" value="{{ $address->label }}" placeholder="Label">
                                </div>
                                <div class="col-md-6">
                                    <input type="text" name="line1" class="form-control" value="{{ $address->line1 }}" placeholder="Line 1" required>
                                </div>
                                <div class="col-md-6">
                                    <input type="text" name="line2" class="form-control" value="{{ $address->line2 }}" placeholder="Line 2">
                                </div>
                                <div class="col-md-6">
                                    <input type="text" name="city" class="form-control" value="{{ $address->city }}" placeholder="City" required>
                                </div>
                                <div class="col-md-6">
                                    <input type="text" name="state" class="form-control" value="{{ $address->state }}" placeholder="State">
                                </div>
                                <div class="col-md-6">
                                    <input type="text" name="postal_code" class="form-control" value="{{ $address->postal_code }}" placeholder="Postal code">
                                </div>
                                <div class="col-md-6">
                                    <input type="text" name="country" class="form-control" value="{{ $address->country }}" placeholder="Country">
                                </div>
                                <div class="col-md-6 d-flex align-items-center">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_default" value="1" {{ $address->is_default ? 'checked' : '' }}>
                                        <label class="form-check-label">Default</label>
                                    </div>
                                </div>
                            </div>
                            <button class="btn btn-sm btn-outline-secondary mt-2">Update Address</button>
                        </form>
                    </div>
                @empty
                    <div class="text-muted">No saved addresses yet.</div>
                @endforelse
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h5 class="mb-3">Add Address</h5>
                <form method="post" action="{{ route('shop.account.addresses.store') }}">
                    @csrf
                    <div class="row g-2">
                        <div class="col-md-6">
                            <input type="text" name="label" class="form-control" placeholder="Label (Home, Work)">
                        </div>
                        <div class="col-md-6">
                            <input type="text" name="line1" class="form-control" placeholder="Line 1" required>
                        </div>
                        <div class="col-md-6">
                            <input type="text" name="line2" class="form-control" placeholder="Line 2">
                        </div>
                        <div class="col-md-6">
                            <input type="text" name="city" class="form-control" placeholder="City" required>
                        </div>
                        <div class="col-md-6">
                            <input type="text" name="state" class="form-control" placeholder="State">
                        </div>
                        <div class="col-md-6">
                            <input type="text" name="postal_code" class="form-control" placeholder="Postal code">
                        </div>
                        <div class="col-md-6">
                            <input type="text" name="country" class="form-control" placeholder="Country" value="Nigeria">
                        </div>
                        <div class="col-md-6 d-flex align-items-center">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_default" value="1">
                                <label class="form-check-label">Make default</label>
                            </div>
                        </div>
                    </div>
                    <button class="btn btn-sm btn-primary mt-2">Add Address</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
