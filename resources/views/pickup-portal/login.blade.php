@extends('layouts.pickup-portal', ['title' => 'Station Login'])
@section('content')
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h4 class="mb-1 text-center"><i class="bi bi-geo-alt me-1"></i>Staff Login</h4>
                <p class="text-muted text-center small mb-4">Select your station and enter your PIN.</p>

                <form method="post" action="{{ route('pickup-portal.login.post') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Pickup Station</label>
                        <select name="pickup_station_id" class="form-select @error('pickup_station_id') is-invalid @enderror" required>
                            <option value="">- Select station -</option>
                            @foreach($stations as $s)
                                <option value="{{ $s->id }}" @selected(old('pickup_station_id') == $s->id)>{{ $s->name }}</option>
                            @endforeach
                        </select>
                        @error('pickup_station_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-4">
                        <label class="form-label">PIN</label>
                        <input type="password" name="pin" class="form-control @error('pin') is-invalid @enderror"
                               placeholder="Enter your staff PIN" autocomplete="current-password" required>
                        @error('pin')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <button class="btn btn-primary w-100">Login</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
