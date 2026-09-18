@extends('layouts.shop', ['title' => $creation->title . ' | Custom Creations'])

@section('content')
<main class="container py-4" style="flex: 1 0 auto;">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('shop.custom-creations.index') }}">Custom Creations</a></li>
            <li class="breadcrumb-item active">{{ $creation->title }}</li>
        </ol>
    </nav>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card border-0 shadow-sm">
                <div style="aspect-ratio:3/4; overflow:hidden; border-radius:.375rem;">
                    <img src="{{ $creation->image_url }}"
                         alt="{{ $creation->title }}"
                         class="w-100 h-100"
                         style="object-fit:cover;">
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <h1 class="h3 mb-2">{{ $creation->title }}</h1>

            @if ($creation->category && isset($categories[$creation->category]))
                <span class="badge bg-primary mb-3">{{ $categories[$creation->category] }}</span>
            @endif

            @if ($creation->price)
                <p class="fs-4 fw-bold" style="color:var(--kid-pink);">@if ($creation->is_price_from)From @endif&#8358;{{ number_format($creation->price, 2) }}</p>
            @endif

            @if ($creation->description)
                <div class="mb-4">
                    <p class="text-muted">{{ $creation->description }}</p>
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
