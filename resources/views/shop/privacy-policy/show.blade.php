@extends('layouts.shop', ['title' => 'Privacy Policy'])
@section('content')
<div class="mb-4">
    <h2 class="fw-bold">Privacy Policy</h2>
</div>

@if($policy)
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4 p-md-5">
            {!! nl2br(e($policy)) !!}
        </div>
    </div>
@else
    <div class="text-center text-muted py-5">
        <i class="bi bi-info-circle fs-1 d-block mb-2"></i>
        Privacy policy information will be available soon.
    </div>
@endif
@endsection
