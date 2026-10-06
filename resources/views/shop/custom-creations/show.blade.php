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

            @if ($creation->age_range)
                <p class="text-muted mb-2"><i class="bi bi-hourglass-split me-1"></i>Ages {{ $creation->age_range }}</p>
            @endif

            @php $state = $creation->availability; @endphp
            <span class="badge mb-3
                @if ($state === 'available') bg-success
                @elseif ($state === 'out_of_stock') bg-warning text-dark
                @else bg-info text-dark @endif">
                @if ($state === 'available') Available to Order
                @elseif ($state === 'out_of_stock') Currently Out of Stock
                @else Can Be Created on Request @endif
            </span>

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
                    @if ($state === 'available')
                        <p class="text-muted small mb-3">Ready to buy this exact dress, or have us create something just for you.</p>
                        <a href="{{ route('shop.products.show', $creation->product_id) }}" class="btn btn-primary px-4">
                            <i class="bi bi-bag-check me-1"></i> View &amp; Order
                        </a>
                        <a href="{{ route('shop.custom-frock.create') }}" class="btn btn-outline-primary px-4">
                            <i class="bi bi-scissors me-1"></i> Start Custom Order
                        </a>
                    @elseif ($state === 'out_of_stock')
                        <p class="text-muted small mb-3">This dress is temporarily out of stock — but we can still create one for you.</p>
                        <a href="{{ route('shop.custom-frock.create') }}" class="btn btn-primary px-4">
                            <i class="bi bi-scissors me-1"></i> Request This Creation
                        </a>
                        @if (config('shop.out_of_stock_visibility') !== 'hide')
                            <a href="{{ route('shop.products.show', $creation->product_id) }}" class="btn btn-outline-secondary px-4">
                                <i class="bi bi-eye me-1"></i> View Product
                            </a>
                        @endif
                    @else
                        <p class="text-muted small mb-3">This design isn't in stock right now, but we'd love to create it for you.</p>
                        <a href="{{ route('shop.custom-frock.create') }}" class="btn btn-primary px-4">
                            <i class="bi bi-scissors me-1"></i> Request This Creation
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</main>
@endsection
