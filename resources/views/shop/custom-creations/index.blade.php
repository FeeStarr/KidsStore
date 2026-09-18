@extends('layouts.shop', ['title' => 'Custom Creations'])

@section('content')
<main class="container py-4" style="flex: 1 0 auto;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Custom Creations</h1>
            <p class="text-muted mb-0">See what we can create for you.</p>
        </div>
    </div>

    {{-- Category Filter Tabs --}}
    <ul class="nav nav-pills mb-4">
        <li class="nav-item">
            <a class="nav-link {{ !$activeCategory ? 'active' : '' }}" href="{{ route('shop.custom-creations.index') }}">All</a>
        </li>
        @foreach ($categories as $key => $label)
            <li class="nav-item">
                <a class="nav-link {{ $activeCategory === $key ? 'active' : '' }}" href="{{ route('shop.custom-creations.index', ['category' => $key]) }}">{{ $label }}</a>
            </li>
        @endforeach
    </ul>

    @if ($creations->isEmpty())
        <div class="text-center py-5">
            <i class="bi bi-stars display-1 text-muted"></i>
            <h4 class="mt-3 text-muted">No creations to display yet.</h4>
            <p class="text-muted">Check back soon for our latest custom designs!</p>
        </div>
    @else
        <div class="row g-3">
            @foreach ($creations as $creation)
                <div class="col-6 col-md-4 col-lg-3">
                    <a href="{{ route('shop.custom-creations.show', $creation->id) }}" class="text-decoration-none">
                        <div class="card h-100 shadow-sm border-0 creation-card">
                            <div class="position-relative" style="aspect-ratio:3/4; overflow:hidden; border-radius:.375rem .375rem 0 0;">
                                <img src="{{ $creation->showcase_image_url }}"
                                     alt="{{ $creation->showcase_title }}"
                                     class="w-100 h-100"
                                     style="object-fit:cover;" loading="lazy" decoding="async">
                                @if ($creation->showcase_category && isset($categories[$creation->showcase_category]))
                                    <span class="badge bg-primary position-absolute top-0 end-0 m-2">
                                        {{ $categories[$creation->showcase_category] }}
                                    </span>
                                @endif
                            </div>
                            <div class="card-body">
                                <h6 class="card-title mb-1 text-dark">{{ $creation->showcase_title }}</h6>
                                @if ($creation->showcase_price)
                                    <p class="fw-bold mb-0" style="color:var(--kid-pink);">&#8358;{{ number_format($creation->showcase_price, 2) }}</p>
                                @endif
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $creations->withQueryString()->links() }}
        </div>
    @endif
</main>

<style>
.creation-card { transition: transform .25s ease, box-shadow .25s ease; }
.creation-card:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(0,0,0,.12) !important; }
</style>
@endsection
