@php
    $img = $product->catalog_image;
    $stock = (int) $product->stock_quantity;
    $multiVariant = $product->relationLoaded('variants')
        ? $product->variants->count() > 1
        : $product->variants()->count() > 1;
    $defaultVariant = $product->relationLoaded('defaultVariant')
        ? $product->defaultVariant
        : $product->variants()->oldest('id')->first();

    $singleDiscount = min(100, max(0, (float) ($product->discount ?? 0) + (float) ($defaultVariant?->discount ?? 0)));
    $hasDiscount = $singleDiscount > 0;

    $activeVariants = $product->relationLoaded('variants')
        ? $product->variants->where('is_active', true)
        : collect();
    $maxVariantDiscount = $activeVariants->isNotEmpty()
        ? (float) $activeVariants->max('discount')
        : 0;
    $displayDiscount = $multiVariant
        ? min(100, max(0, (float) ($product->discount ?? 0) + $maxVariantDiscount))
        : $singleDiscount;

    $avgRating = (float) ($product->reviews_avg_rating ?? 0);
    $reviewsCount = (int) ($product->reviews_count ?? 0);

    $deal = app(\App\Services\DealService::class)->activeDealForProduct($product);
@endphp
<div class="card product-card h-100 shadow-sm border-0 {{ $stock <= 0 ? 'opacity-50' : '' }}">
    <a href="{{ route('shop.products.show', $product) }}" class="text-decoration-none text-dark">
        <div class="img-wrap m-2 position-relative">
            @if($stock <= 0)
                <span class="position-absolute top-50 start-50 translate-middle badge bg-secondary fs-6 z-1 px-3 py-2">Sold out</span>
            @endif
            @if($img)
                <img src="{{ $img }}" alt="{{ $product->name }}">
            @else
                <div class="d-flex align-items-center justify-content-center text-muted h-100"><i class="bi bi-image fs-1"></i></div>
            @endif
        </div>
        <div class="card-body pt-0">
            <div class="small text-muted">{{ $product->category?->name }}</div>
            <h6 class="mb-1">{{ $product->name }}</h6>
            @if(!($product->is_returnable ?? true))
                <span class="badge bg-warning text-dark fw-semibold" style="font-size:10px;"><i class="bi bi-exclamation-triangle me-1"></i>Non-returnable</span>
            @endif
            @if($reviewsCount > 0 && $avgRating > 0)
                <div class="small text-muted mb-1">&#9733; {{ number_format($avgRating, 1) }} ({{ $reviewsCount }})</div>
            @endif
            <div>
                @if($deal)
                    @php
                        $base = $multiVariant
                            ? (float) $product->price_from
                            : (float) ($defaultVariant?->selling_price ?? $product->selling_price);
                        $dealBase = $multiVariant
                            ? $product->variants()->where('is_active', true)->get()
                                ->map(fn ($v) => (float) $v->selling_price)
                                ->filter(fn ($p) => $p > 0)
                                ->min() ?? $base
                            : $base;
                        $dealPrice = $deal->priceFor($dealBase);
                        $saved     = max(0, $dealBase - $dealPrice);
                    @endphp
                    @if($multiVariant)
                        <small class="text-muted">from</small>
                    @endif
                    <span class="price-old">&#8358;{{ number_format($dealBase, 2) }}</span>
                    <strong class="text-danger ms-1">&#8358;{{ number_format($dealPrice, 2) }}</strong>
                    @if($saved > 0)
                        <div class="small text-danger fw-semibold">{{ $deal->badge_text }} &middot; Save &#8358;{{ number_format($saved, 2) }}</div>
                    @else
                        <span class="badge bg-danger ms-1">{{ $deal->badge_text }}</span>
                    @endif
                @elseif($multiVariant)
                    <small class="text-muted">from</small>
                    <strong>&#8358;{{ number_format($product->price_from, 2) }}</strong>
                    @if($displayDiscount > 0)
                        <span class="badge bg-danger-subtle text-danger ms-1">up to -{{ rtrim(rtrim(number_format($displayDiscount, 2), '0'), '.') }}%</span>
                    @endif
                @elseif($hasDiscount)
                    @php
                        $basePrice = (float) ($defaultVariant?->selling_price ?? $product->selling_price);
                        $netPrice = $basePrice * (1 - ($singleDiscount / 100));
                    @endphp
                    <span class="price-old">&#8358;{{ number_format($basePrice, 2) }}</span>
                    <strong class="text-danger ms-1">&#8358;{{ number_format($netPrice, 2) }}</strong>
                    <span class="badge bg-danger-subtle text-danger ms-1">-{{ rtrim(rtrim(number_format($singleDiscount,2), '0'), '.') }}%</span>
                @else
                    <strong>&#8358;{{ number_format((float) ($defaultVariant?->selling_price ?? $product->selling_price), 2) }}</strong>
                @endif
            </div>
        </div>
    </a>
    <div class="card-footer bg-white border-0 pt-0 d-flex justify-content-between align-items-center">
        @if($stock <= 0)
            <button class="btn btn-sm btn-secondary" disabled>Sold out</button>
        @elseif($multiVariant)
            <a href="{{ route('shop.products.show', $product) }}" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-eye"></i> Choose options
            </a>
            <small class="text-success">In stock</small>
        @elseif($defaultVariant)
            <form action="{{ route('shop.cart.add', $defaultVariant) }}" method="post" class="m-0">
                @csrf
                <button class="btn btn-sm btn-primary"><i class="bi bi-bag-plus"></i> Add</button>
            </form>
            <small class="text-success">In stock</small>
        @else
            <button class="btn btn-sm btn-secondary" disabled>Sold out</button>
        @endif
    </div>
</div>
