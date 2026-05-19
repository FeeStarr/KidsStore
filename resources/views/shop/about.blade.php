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

{{-- Why Us --}}
<div class="text-center mb-4">
    <span class="section-title fs-5"><i class="bi bi-stars text-warning"></i> Why Parents Love Us</span>
</div>
<div class="row g-3 mb-5">
    <div class="col-6 col-md-3">
        <div class="kid-tile tile-pink h-100">
            <i class="bi bi-shield-check"></i>
            <strong>Safe Products</strong>
            <small>Every item quality-tested</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kid-tile tile-blue h-100">
            <i class="bi bi-truck"></i>
            <strong>Fast Delivery</strong>
            <small>Right to your door</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kid-tile tile-green h-100">
            <i class="bi bi-arrow-repeat"></i>
            <strong>Easy Returns</strong>
            <small>No-stress policy</small>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kid-tile tile-purple h-100">
            <i class="bi bi-emoji-smile"></i>
            <strong>Kid Approved</strong>
            <small>Smiles every time</small>
        </div>
    </div>
</div>

{{-- Contact --}}
@if($about->email || $about->phone || $about->address)
<div class="card border-0 shadow-sm mb-5" style="border-radius:1.25rem;">
    <div class="card-body p-4">
        <h4 class="mb-4" style="font-family:'Fredoka',sans-serif;"><i class="bi bi-envelope-heart-fill text-danger me-2"></i>Get in Touch</h4>
        <div class="row g-3">
            @if($about->email)
            <div class="col-md-4 d-flex align-items-center gap-3">
                <span class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                      style="width:44px;height:44px;background:var(--kid-pink);color:#fff;font-size:1.2rem;">
                    <i class="bi bi-envelope-fill"></i>
                </span>
                <div>
                    <div class="small text-muted">Email</div>
                    <a href="mailto:{{ $about->email }}" class="fw-semibold text-decoration-none text-dark">{{ $about->email }}</a>
                </div>
            </div>
            @endif
            @if($about->phone)
            <div class="col-md-4 d-flex align-items-center gap-3">
                <span class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                      style="width:44px;height:44px;background:var(--kid-blue);color:#fff;font-size:1.2rem;">
                    <i class="bi bi-telephone-fill"></i>
                </span>
                <div>
                    <div class="small text-muted">Phone</div>
                    <a href="tel:{{ preg_replace('/\s+/', '', $about->phone) }}" class="fw-semibold text-decoration-none text-dark">{{ $about->phone }}</a>
                </div>
            </div>
            @endif
            @if($about->address)
            <div class="col-md-4 d-flex align-items-center gap-3">
                <span class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                      style="width:44px;height:44px;background:var(--kid-green);color:#fff;font-size:1.2rem;">
                    <i class="bi bi-geo-alt-fill"></i>
                </span>
                <div>
                    <div class="small text-muted">Address</div>
                    <span class="fw-semibold">{{ $about->address }}</span>
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
