@extends('layouts.shop', ['title' => $deal->title])
@section('content')

@php
    $isUpcoming = $deal->computedStatus() === 'scheduled';
@endphp

<nav aria-label="breadcrumb" class="small mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('shop.home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('shop.deals.index') }}">Deals</a></li>
        <li class="breadcrumb-item active">{{ $deal->title }}</li>
    </ol>
</nav>

<div class="card border-0 shadow-sm overflow-hidden mb-4">
    @if($deal->banner_image)
        <img src="{{ asset('storage/'.$deal->banner_image) }}" style="height:220px;object-fit:cover" alt="{{ $deal->title }}">
    @endif
    <div class="card-body p-4">
        <span class="badge bg-danger fs-6">{{ $deal->badge_text }}</span>
        <h3 class="mt-2 mb-1">{{ $deal->title }}</h3>
        @if($deal->description)
            <p class="mb-2">{{ $deal->description }}</p>
        @endif
        <div class="text-muted small">
            @if($isUpcoming)
                <i class="bi bi-calendar-event me-1"></i> Starts {{ $deal->starts_at->format('M d, Y g:i A') }}
            @else
                <i class="bi bi-calendar-event me-1"></i>
                @if($deal->starts_at) From {{ $deal->starts_at->format('M d, Y g:i A') }} &middot; @endif
                @if($deal->ends_at) Ends {{ $deal->ends_at->format('M d, Y g:i A') }} ({{ $deal->ends_at->diffForHumans(['parts' => 2, 'short' => true]) }}) @endif
            @endif
        </div>
    </div>
</div>

<h4 class="mb-3">{{ $isUpcoming ? 'Products in this deal' : 'Deal Products' }}</h4>

<div class="row g-3">
    @foreach($deal->products as $p)
        <div class="col-6 col-md-4 col-lg-3">@include('shop.partials.product-card', ['product' => $p])</div>
    @endforeach
</div>

@endsection
