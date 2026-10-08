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
                @php $state = $creation->availability; @endphp
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="card h-100 shadow-sm border-0 creation-card">
                        <a href="{{ route('shop.custom-creations.show', $creation->id) }}" class="text-decoration-none">
                            <div class="position-relative" style="aspect-ratio:3/4; overflow:hidden; border-radius:.375rem .375rem 0 0;">
                                <img src="{{ $creation->image_url }}"
                                     alt="{{ $creation->title }}"
                                     class="w-100 h-100"
                                     style="object-fit:cover;" loading="lazy" decoding="async">
                                @if ($creation->category && isset($categories[$creation->category]))
                                    <span class="badge bg-primary position-absolute top-0 end-0 m-2">
                                        {{ $categories[$creation->category] }}
                                    </span>
                                @endif
                            </div>
                        </a>
                        <div class="card-body d-flex flex-column">
                            <a href="{{ route('shop.custom-creations.show', $creation->id) }}" class="text-decoration-none">
                                <h6 class="card-title mb-1 text-dark">{{ $creation->title }}</h6>
                            </a>
                            @if ($creation->age_range)
                                <small class="text-muted mb-1"><i class="bi bi-hourglass-split me-1"></i>Ages {{ $creation->age_range }}</small>
                            @endif
                            @if ($creation->price)
                                <p class="fw-bold mb-1" style="color:var(--kid-pink);">@if ($creation->is_price_from)From @endif&#8358;{{ number_format($creation->price, 2) }}</p>
                            @endif
                            @if ($state !== 'request_only')
                                <span class="badge align-self-start mb-2
                                    @if ($state === 'available') bg-success
                                    @elseif ($state === 'out_of_stock') bg-warning text-dark
                                    @else bg-info text-dark @endif">
                                    @if ($state === 'available') Available to Order
                                    @elseif ($state === 'out_of_stock') Currently Out of Stock
                                    @else Can Be Created on Request @endif
                                </span>
                            @endif
                            <div class="d-flex flex-column gap-2 mt-auto">
                                @if ($state === 'available')
                                    <a href="{{ route('shop.products.show', $creation->product_id) }}" class="btn btn-primary btn-sm w-100">View &amp; Order</a>
                                    <a href="{{ route('shop.custom-frock.create') }}" class="btn btn-outline-primary btn-sm w-100">Start Custom Order</a>
                                @elseif ($state === 'out_of_stock')
                                    <a href="{{ route('shop.custom-frock.create') }}" class="btn btn-primary btn-sm w-100">Request This Creation</a>
                                    @if (config('shop.out_of_stock_visibility') !== 'hide')
                                        <a href="{{ route('shop.products.show', $creation->product_id) }}" class="btn btn-outline-secondary btn-sm w-100">View Product</a>
                                    @endif
                                @else
                                    <a href="{{ route('shop.custom-frock.create') }}" class="btn btn-primary btn-sm w-100">Request This Creation</a>
                                @endif
                            </div>
                        </div>
                    </div>
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
