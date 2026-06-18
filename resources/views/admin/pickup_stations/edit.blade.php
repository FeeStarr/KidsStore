@extends('layouts.admin', ['title' => 'Edit Pickup Station'])
@section('content')
<h3 class="mb-3">Edit Pickup Station</h3>

<div class="card" style="max-width:600px">
    <div class="card-body">
        <form method="post" action="{{ route('admin.pickup-stations.update', $pickupStation) }}">
            @csrf @method('PUT')
            @include('admin.pickup_stations._form', ['station' => $pickupStation])
            <div class="d-flex gap-2 mt-3">
                <button class="btn btn-primary">Save Changes</button>
                <a href="{{ route('admin.pickup-stations.index') }}" class="btn btn-link">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
