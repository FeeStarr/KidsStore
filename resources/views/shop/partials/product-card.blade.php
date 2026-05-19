@php
    $img = $product->primaryImage?->url;
    $hasDiscount = (float) $product->discount > 0;
    $stock = (int) $product->stock_quantity;
    $multiVariant = $product->relationLoaded('variants')
        ? $product->variants->count() > 1
        : $product->variants()->count() > 1;
    $defaultVariant = $product->relationLoaded('defaultVariant')
        ? $product->defaultVariant
        : $product->variants()->oldest('id')->first();
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
            <div>
                @if($multiVariant)
                    <small class="text-muted">from</small>
                    <strong>&#8358;{{ number_format($product->price_from, 2) }}</strong>
                @elseif($hasDiscount)
                    <span class="price-old">&#8358;{{ number_format($product->selling_price, 2) }}</span>
                    <strong class="text-danger ms-1">&#8358;{{ number_format($product->net_price, 2) }}</strong>
                    <span class="badge bg-danger-subtle text-danger ms-1">-{{ rtrim(rtrim(number_format($product->discount,2), '0'), '.') }}%</span>
                @else
                    <strong>&#8358;{{ number_format($product->selling_price, 2) }}</strong>
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
            <small class="text-success">{{ $stock }} in stock</small>
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
