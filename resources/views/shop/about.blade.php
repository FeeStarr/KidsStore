@extends('layouts.shop', ['title' => 'About Us'])

@section('content')

{{-- Hero --}}
<div class="hero mb-5">
    <div class="row align-items-center position-relative" style="z-index:1;">
        <div class="col-md-8">
            <h1 class="fw-bold mb-3">{{ $about->hero_title }}</h1>
            <p class="lead mb-0 opacity-90">{{ $about->hero_subtitle }}</p>
        </div>
        <div class="col-md-4 text-center d-none d-md-block">
            <span class="floaty" style="font-size:7rem;">&#128085;</span>
            <span class="floaty" style="font-size:7rem;">&#128086;</span>
        </div>
    </div>
</div>

<div class="row g-4 mb-5">

    {{-- Our Mission --}}
    @if($about->mission)
    <div class="col-md-6">
        <div class="card h-100 border-0 shadow-sm" style="border-radius:1.25rem;">
            <div class="card-body p-4">
                <div class="d-flex align-items-center mb-3">
                    <span class="rounded-circle d-flex align-items-center justify-content-center me-3"
                          style="width:48px;height:48px;background:var(--kid-purple);color:#fff;font-size:1.4rem;">
                        <i class="bi bi-rocket-takeoff-fill"></i>
                    </span>
                    <h4 class="mb-0" style="font-family:'Fredoka',sans-serif;">Our Mission</h4>
                </div>
                <p class="text-muted mb-0" style="line-height:1.8;">{{ $about->mission }}</p>
            </div>
        </div>
    </div>
    @endif

</div>

{{-- Why Us — feature strip style (matching home page) --}}
<div class="feature-strip p-3 p-md-4 mb-5">
    <div class="text-center mb-3">
        <span class="section-title fs-5"><i class="bi bi-stars text-warning"></i> Why Parents Love Us</span>
    </div>
    <div class="fill-row gap-3 text-center">
        <div class="feat fill-tile px-2">
            <i style="background:var(--kid-pink);"><i class="bi bi-shield-check"></i></i>
            <div class="mt-2"><strong>Safe Products</strong><br><small class="text-muted">Every item quality-tested</small></div>
        </div>
        <div class="feat fill-tile px-2">
            <i style="background:var(--kid-blue);"><i class="bi bi-truck"></i></i>
            <div class="mt-2"><strong>Fast Delivery</strong><br><small class="text-muted">Right to your door</small></div>
        </div>
        <div class="feat fill-tile px-2">
            <i style="background:var(--kid-green);"><i class="bi bi-arrow-repeat"></i></i>
            <div class="mt-2"><strong>Returns Available</strong><br><small class="text-muted">Subject to our return policy</small></div>
        </div>
        <div class="feat fill-tile px-2">
            <i style="background:var(--kid-purple);"><i class="bi bi-emoji-smile"></i></i>
            <div class="mt-2"><strong>Kid Approved</strong><br><small class="text-muted">Smiles every time</small></div>
        </div>
    </div>
</div>

{{-- Contact — fetched from global settings --}}
@php
    $contactEmail   = \App\Models\Setting::get('contact_email', '');
    $contactPhone   = \App\Models\Setting::get('contact_phone', '');
    $contactAddress = \App\Models\Setting::get('contact_address', '');
@endphp
@if($contactEmail || $contactPhone || $contactAddress)
<div class="card border-0 shadow-sm mb-5" style="border-radius:1.25rem;">
    <div class="card-body p-4">
        <h4 class="mb-4" style="font-family:'Fredoka',sans-serif;"><i class="bi bi-envelope-heart-fill text-danger me-2"></i>Get in Touch</h4>
        <div class="row g-3">
            @if($contactEmail)
            <div class="col-md-4 d-flex align-items-center gap-3">
                <span class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                      style="width:44px;height:44px;background:var(--kid-pink);color:#fff;font-size:1.2rem;">
                    <i class="bi bi-envelope-fill"></i>
                </span>
                <div>
                    <div class="small text-muted">Email</div>
                    <a href="mailto:{{ $contactEmail }}" class="fw-semibold text-decoration-none text-dark">{{ $contactEmail }}</a>
                </div>
            </div>
            @endif
            @if($contactPhone)
            <div class="col-md-4 d-flex align-items-center gap-3">
                <span class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                      style="width:44px;height:44px;background:var(--kid-blue);color:#fff;font-size:1.2rem;">
                    <i class="bi bi-telephone-fill"></i>
                </span>
                <div>
                    <div class="small text-muted">Phone</div>
                    <a href="tel:{{ preg_replace('/\s+/', '', $contactPhone) }}" class="fw-semibold text-decoration-none text-dark">{{ $contactPhone }}</a>
                </div>
            </div>
            @endif
            @if($contactAddress)
            <div class="col-md-4 d-flex align-items-center gap-3">
                <span class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                      style="width:44px;height:44px;background:var(--kid-green);color:#fff;font-size:1.2rem;">
                    <i class="bi bi-geo-alt-fill"></i>
                </span>
                <div>
                    <div class="small text-muted">Address</div>
                    <span class="fw-semibold">{{ $contactAddress }}</span>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endif

{{-- CTA --}}
<div class="hero mb-2" style="background: linear-gradient(120deg, var(--kid-yellow), var(--kid-orange)); padding: 2.5rem;">
    <div class="row align-items-center position-relative" style="z-index:1;">
        <div class="col-md-8">
            <h2 class="fw-bold mb-2" style="color:#3a1f5d;">Ready to shop? <span class="floaty">&#127873;</span></h2>
            <p class="mb-0" style="color:#3a1f5d;">Browse our handpicked collection of toys, clothes, books & more.</p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <a href="{{ route('shop.products.index') }}" class="btn btn-light btn-lg" style="border-radius:50px;color:var(--kid-purple);font-weight:600;">
                <i class="bi bi-bag-heart-fill"></i> Shop Now
            </a>
        </div>
    </div>
</div>

@endsection
