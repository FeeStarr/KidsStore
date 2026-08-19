@extends('layouts.shop', ['title' => 'Hot Deals'])
@section('content')

<div class="hero text-center mb-4">
    <h1 class="fw-bold">HOT DEALS <span class="floaty">🔥</span></h1>
    <p class="mb-3 fs-5">Special prices on selected kids' fashion.</p>
    <a href="#deals" class="btn btn-light"><i class="bi bi-tag-fill me-1"></i> Shop Deals</a>
</div>

@if($deals->isEmpty())
    <div class="alert alert-light border text-center py-5">
        No active deals right now. <a href="{{ route('shop.products.index') }}">Browse all products</a>.
    </div>
@endif

<section id="deals" class="mb-5">
    <div class="row g-4">
        @foreach($deals as $deal)
            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100 overflow-hidden">
                    @if($deal->banner_image)
                        <a href="{{ route('shop.deals.show', $deal) }}">
                            <img src="{{ asset('storage/'.$deal->banner_image) }}" class="card-img-top" style="height:160px;object-fit:cover" alt="{{ $deal->title }}" loading="lazy" decoding="async">
                        </a>
                    @else
                        <div class="bg-danger-subtle d-flex align-items-center justify-content-center" style="height:160px">
                            <span class="badge bg-danger fs-5">{{ $deal->badge_text }}</span>
                        </div>
                    @endif
                    <div class="card-body">
                        <span class="badge bg-danger">{{ $deal->badge_text }}</span>
                        @if($deal->is_featured)<span class="badge bg-warning text-dark">Featured</span>@endif
                        <h5 class="mt-2 mb-1">{{ $deal->title }}</h5>
                        @if($deal->description)
                            <p class="small text-muted mb-2">{{ \Illuminate\Support\Str::limit($deal->description, 90) }}</p>
                        @endif
                        <div class="small text-muted mb-2">
                            <i class="bi bi-calendar-event me-1"></i>
                            @if($deal->ends_at)
                                Ends {{ $deal->ends_at->diffForHumans(['parts' => 2, 'short' => true]) }}
                            @else
                                No end date
                            @endif
                        </div>
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="small text-muted">{{ $deal->products_count }} product{{ $deal->products_count === 1 ? '' : 's' }}</span>
                            <a href="{{ route('shop.deals.show', $deal) }}" class="btn btn-sm btn-primary">Shop Now</a>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</section>

@if($upcoming->isNotEmpty())
<section class="mb-4">
    <h4 class="mb-3"><i class="bi bi-clock-history me-2"></i>Upcoming Deals</h4>
    <div class="row g-3">
        @foreach($upcoming as $deal)
            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <span class="badge bg-primary">{{ $deal->badge_text }}</span>
                        <h6 class="mt-2 mb-1">{{ $deal->title }}</h6>
                        <div class="small text-muted">
                            <i class="bi bi-calendar-event me-1"></i>
                            Starts {{ $deal->starts_at->format('M d, g:i A') }}
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</section>
@endif

@endsection
