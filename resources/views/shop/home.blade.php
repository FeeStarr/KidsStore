@extends('layouts.shop', ['title' => 'Welcome'])
@section('content')

<div class="hero mb-4">
    <div class="row align-items-center position-relative" style="z-index:1;">
        <div class="col-md-7">
            <span class="badge bg-white text-dark mb-3 px-3 py-2" style="border-radius:50px;">
                <i class="bi bi-stars text-warning"></i> Fun for every age
            </span>
            <h1 class="fw-bold mb-3">Where little dreams<br><span style="color:var(--kid-yellow);">come to play!</span></h1>
            <p class="lead mb-4 opacity-90">Toys, clothes, books, shoes & more — handpicked for happy kids and delivered to your door.</p>
            <a href="{{ route('shop.products.index') }}" class="btn btn-light btn-lg me-2">
                <i class="bi bi-bag-heart-fill"></i> Shop now
            </a>
            <a href="{{ route('shop.products.index', ['sort' => 'price_asc']) }}" class="btn btn-outline-light btn-lg" style="border-radius:50px;">
                <i class="bi bi-tag-fill"></i> See deals
            </a>
        </div>
        <div class="col-md-5 text-center d-none d-md-block">
            <span class="floaty" style="font-size:8rem;">&#127881;</span>
        </div>
    </div>
</div>

<div class="feature-strip p-3 p-md-4 mb-5">
    <div class="row g-3 text-center">
        <div class="col-6 col-md-3 feat">
            <i style="background:var(--kid-pink);"><i class="bi bi-truck"></i></i>
            <div class="mt-2"><strong>Fast Delivery</strong><br><small class="text-muted">Right to your door</small></div>
        </div>
        <div class="col-6 col-md-3 feat">
            <i style="background:var(--kid-blue);"><i class="bi bi-shield-check"></i></i>
            <div class="mt-2"><strong>Safe & Trusted</strong><br><small class="text-muted">Quality guaranteed</small></div>
        </div>
        <div class="col-6 col-md-3 feat">
            <i style="background:var(--kid-green);"><i class="bi bi-arrow-repeat"></i></i>
            <div class="mt-2"><strong>Easy Returns</strong><br><small class="text-muted">No-stress policy</small></div>
        </div>
        <div class="col-6 col-md-3 feat">
            <i style="background:var(--kid-purple);"><i class="bi bi-emoji-smile"></i></i>
            <div class="mt-2"><strong>Kid Approved</strong><br><small class="text-muted">Smiles every time</small></div>
        </div>
    </div>
</div>

@if($categories->count())
@php
    $tilePalette = ['tile-pink', 'tile-yellow', 'tile-blue', 'tile-green', 'tile-purple', 'tile-orange'];
    $iconMap = [
        'toys' => 'bi-puzzle-fill',
        'clothing' => 'bi-bag-heart-fill',
        'books' => 'bi-book-half',
        'shoes' => 'bi-bicycle',
        'accessories' => 'bi-gem',
    ];
@endphp
<div class="text-center mb-3">
    <span class="section-title fs-5"><i class="bi bi-grid-3x3-gap-fill text-warning"></i> Shop by Category</span>
</div>
<div class="row g-3 mb-5">
    @foreach($categories as $i => $c)
        @php
            $tile = $tilePalette[$i % count($tilePalette)];
            $slug = strtolower($c->slug ?? '');
            $icon = $iconMap[$slug] ?? 'bi-balloon-heart-fill';
        @endphp
        <div class="col-6 col-md-4 col-lg-2">
            <a href="{{ route('shop.products.index', ['category' => $c->id]) }}" class="text-decoration-none">
                <div class="kid-tile {{ $tile }} h-100">
                    <i class="bi {{ $icon }}"></i>
                    <strong>{{ $c->name }}</strong>
                    <small>{{ $c->products_count }} items</small>
                </div>
            </a>
        </div>
    @endforeach
</div>
@endif

<div class="d-flex justify-content-between align-items-center mb-3">
    <span class="section-title fs-5"><i class="bi bi-stars text-warning"></i> Latest Goodies</span>
    <a href="{{ route('shop.products.index') }}" class="btn btn-sm btn-outline-primary" style="border-radius:50px;">View all <i class="bi bi-arrow-right"></i></a>
</div>
<div class="row g-3 mb-5">
    @forelse($featured as $product)
        <div class="col-6 col-md-3">@include('shop.partials.product-card', ['product' => $product])</div>
    @empty
        <div class="col-12 text-center text-muted py-5">No products available yet.</div>
    @endforelse
</div>

<div class="hero mb-4" style="background: linear-gradient(120deg, var(--kid-yellow), var(--kid-orange)); padding: 2.5rem;">
    <div class="row align-items-center position-relative" style="z-index:1;">
        <div class="col-md-8">
            <h2 class="fw-bold mb-2" style="color:#3a1f5d;">Ready for playtime? <span class="floaty">&#129409;</span></h2>
            <p class="mb-0" style="color:#3a1f5d;">Sign up today and get exclusive deals on your favorite picks.</p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            @guest
                <a href="{{ route('shop.register') }}" class="btn btn-light btn-lg" style="border-radius:50px; color:var(--kid-purple); font-weight:600;">
                    <i class="bi bi-person-plus-fill"></i> Join the fun
                </a>
            @else
                <a href="{{ route('shop.products.index') }}" class="btn btn-light btn-lg" style="border-radius:50px; color:var(--kid-purple); font-weight:600;">
                    <i class="bi bi-bag-heart-fill"></i> Keep shopping
                </a>
            @endguest
        </div>
    </div>
</div>

@endsection

