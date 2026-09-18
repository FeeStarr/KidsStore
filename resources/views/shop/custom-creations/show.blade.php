@extends('layouts.shop', ['title' => $creation->showcase_title . ' | Custom Creations'])

@section('content')
<main class="container py-4" style="flex: 1 0 auto;">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('shop.custom-creations.index') }}">Custom Creations</a></li>
            <li class="breadcrumb-item active">{{ $creation->showcase_title }}</li>
        </ol>
    </nav>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card border-0 shadow-sm">
                <div style="aspect-ratio:3/4; overflow:hidden; border-radius:.375rem;">
                    <img src="{{ $creation->showcase_image_url }}"
                         alt="{{ $creation->showcase_title }}"
                         class="w-100 h-100"
                         style="object-fit:cover;">
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <h1 class="h3 mb-2">{{ $creation->showcase_title }}</h1>

            @if ($creation->showcase_category && isset($categories[$creation->showcase_category]))
                <span class="badge bg-primary mb-3">{{ $categories[$creation->showcase_category] }}</span>
            @endif

            @if ($creation->showcase_price)
                <p class="fs-4 fw-bold" style="color:var(--kid-pink);">&#8358;{{ number_format($creation->showcase_price, 2) }}</p>
            @endif

            @if ($creation->showcase_description)
                <div class="mb-4">
                    <p class="text-muted">{{ $creation->showcase_description }}</p>
                </div>
            @endif

            <div class="card border-0 shadow-sm bg-light">
                <div class="card-body text-center">
                    <h5 class="mb-2">Love this design?</h5>
                    <p class="text-muted small mb-3">Let us create something special for you too.</p>
                    <a href="{{ route('shop.custom-frock.create') }}" class="btn btn-primary px-4">
                        <i class="bi bi-scissors me-1"></i> Start Custom Order
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>
@endsection
