@extends('layouts.shop', ['title' => $product->name])
@section('content')

@php
    $product->loadMissing('brandRef', 'variants.inventory', 'variants.image', 'variants.images', 'variants.ageRange', 'variants.sizeRef', 'variants.colorRef', 'images');
    $variants = $product->variants->filter(fn ($v) => $v->is_active)->values();
    $defaultVariant = $variants->first() ?? $product->variants->first();

    // Current cart quantities per variant (keyed by variant id).
    $cartQtys = [];
    try {
        $cart = app(\App\Services\CartService::class);
        foreach ($variants as $v) {
            $q = $cart->getQty($v->id);
            if ($q > 0) $cartQtys[$v->id] = $q;
        }
    } catch (\Throwable $e) {}

    // Group thumbnails by color — one thumbnail button per unique color.
    $thumbVariants = $variants->groupBy(fn ($v) => $v->colorRef?->name ?? 'Default');

    // Per-color fallback gallery. When a variant has no gallery of its own we
    // fall back to the images belonging to the same colour group (mirrors the
    // thumbnails), never the whole product gallery — otherwise same-age variants
    // of a different colour would always show the primary/other-colour images.
    $colorImgs = [];
    foreach ($thumbVariants as $colorName => $colorVars) {
        $gallery = collect();
        foreach ($colorVars as $gv) {
            if ($gv->image) {
                $gallery->push($gv->image->url);
            }
            foreach ($gv->images as $gi) {
                $gallery->push($gi->url);
            }
        }
        $colorImgs[$colorName] = $gallery->unique()->values()->all();
    }

    // Variant data for JS resolver — each row IS the full (color + age + size) combo
    $variantsData = $variants->map(function ($v) use ($product, $cartQtys, $colorImgs) {
        $imgs = $v->images->isNotEmpty()
            ? $v->images->map(fn ($i) => $i->url)->all()
            : ($colorImgs[$v->colorRef?->name ?? 'Default']
                ?: $product->images->map(fn ($i) => $i->url)->all());
        return [
            'id'            => $v->id,
            'sku'           => $v->sku,
            'color'         => $v->colorRef?->name,
            'color_id'      => $v->color_id,
            'size'          => $v->sizeRef?->name,
            'size_id'       => $v->size_id,
            'age'           => $v->ageRange?->name,
            'age_range_id'  => $v->age_range_id,
            'options'       => (object) ((array) $v->options),
            'selling_price' => (float) $v->selling_price,
            'net_price'     => (float) $v->net_price,
            'discount'      => (float) $v->effective_discount,
            'variant_discount' => (float) $v->discount,
            'product_discount' => (float) ($v->product?->discount ?? 0),
            'stock'         => (int) ($v->inventory?->quantity ?? 0),
            'in_cart'       => (int) ($cartQtys[$v->id] ?? 0),
            'image_url'     => $v->image?->url,
            'images'        => $imgs,
            'options_label' => $v->options_label,
        ];
    })->values();

    // Pre-compute the representative image per color group for thumbnail buttons.
    $thumbImgs = [];
    foreach ($thumbVariants as $colorName => $colorVars) {
        $tImg = null;
        foreach ($colorVars as $gv) {
            if ($gv->image) { $tImg = $gv->image->url; break; }
            if ($gv->images->isNotEmpty()) { $tImg = $gv->images->first()->url; break; }
        }
        $thumbImgs[$colorName] = $tImg ?? ($product->images->first()?->url ?? '');
    }

    $hasDiscount = (float) ($defaultVariant?->effective_discount ?? 0) > 0;
    $stock       = (int) ($defaultVariant?->inventory?->quantity ?? 0);
    $inCart      = (int) ($cartQtys[$defaultVariant?->id ?? 0] ?? 0);
    $remaining   = max($stock - $inCart, 0);
    $avg         = (float) $product->average_rating;
    $count       = (int) $product->reviews_count;
    $myReview    = auth()->check()
        ? $product->reviews->firstWhere('customer_id', auth()->id())
        : null;
    $canReview   = auth()->check()
        ? \App\Models\Order::where('customer_id', auth()->id())
            ->where('status', 'delivered')
            ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))
            ->exists()
        : false;
    $productBrand = $product->brandRef?->name ?: $product->brand;
@endphp

<nav aria-label="breadcrumb" class="small mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('shop.home') }}">Home</a></li>
        <li class="breadcrumb-item"><a href="{{ route('shop.products.index') }}">Shop</a></li>
        @if($product->category)
            <li class="breadcrumb-item"><a href="{{ route('shop.products.index', ['category' => $product->category_id]) }}">{{ $product->category->name }}</a></li>
        @endif
        <li class="breadcrumb-item active">{{ $product->name }}</li>
    </ol>
</nav>

<div class="row g-4 mb-5">
    <div class="col-md-6">
        @if($product->images->count())
            <div id="prod-carousel" class="carousel slide" data-bs-ride="carousel">
                <div class="carousel-inner rounded shadow-sm">
                    @foreach($product->images as $i => $img)
                        <div class="carousel-item @if($i===0) active @endif">
                            <img src="{{ $img->url }}" class="d-block w-100" style="aspect-ratio:1/1;object-fit:cover" alt="">
                        </div>
                    @endforeach
                </div>
                @if($product->images->count() > 1)
                    <button class="carousel-control-prev" data-bs-target="#prod-carousel" data-bs-slide="prev"><span class="carousel-control-prev-icon"></span></button>
                    <button class="carousel-control-next" data-bs-target="#prod-carousel" data-bs-slide="next"><span class="carousel-control-next-icon"></span></button>
                @endif
            </div>
        @else
            <div class="bg-light rounded text-muted text-center py-5"><i class="bi bi-image fs-1"></i></div>
        @endif
    </div>

    <div class="col-md-6">
        <h2 class="mb-1">{{ $product->name }}</h2>
        @if(!($product->is_returnable ?? true))
            <div class="mb-2"><span class="badge bg-warning text-dark fw-semibold"><i class="bi bi-exclamation-triangle me-1"></i>Non-returnable item</span></div>
        @endif
        <div class="text-muted small mb-2">
            @if($productBrand) Brand: {{ $productBrand }} @endif
        </div>

        <div class="mb-2">
            @php($full = floor($avg)) @php($half = ($avg - $full) >= 0.5)
            <span class="stars">
                @for($i=1; $i<=5; $i++)
                    @if($i <= $full)<i class="bi bi-star-fill"></i>
                    @elseif($i==$full+1 && $half)<i class="bi bi-star-half"></i>
                    @else<i class="bi bi-star"></i>@endif
                @endfor
            </span>
            <small class="text-muted">{{ number_format($avg, 1) }} ({{ $count }} reviews)</small>
        </div>

        <div class="mb-3" data-pdp="price-block">
            {{-- All 3 elements always exist in DOM so JS can show/hide for any variant --}}
            <span class="price-old fs-5 {{ $hasDiscount ? '' : 'd-none' }}" data-pdp="price-old">&#8358;{{ number_format($defaultVariant?->selling_price ?? 0, 2) }}</span>
            <span class="fs-3 fw-bold {{ $hasDiscount ? 'text-danger ms-2' : '' }}" data-pdp="price-net">&#8358;{{ number_format($hasDiscount ? $defaultVariant?->net_price ?? 0 : $defaultVariant?->selling_price ?? 0, 2) }}</span>
            <span class="badge bg-danger ms-2 {{ $hasDiscount ? '' : 'd-none' }}" data-pdp="price-badge">-{{ rtrim(rtrim(number_format($defaultVariant?->discount ?? 0, 2), '0'), '.') }}%</span>
        </div>

        <p>{{ $product->description }}</p>

        <div class="mb-2 small text-muted" id="pdp-color-row" data-pdp="color-row"
             style="{{ $defaultVariant?->colorRef?->name ? '' : 'display:none' }}">
            Color: <strong data-pdp="color-name" class="text-dark">{{ $defaultVariant?->colorRef?->name ?? '' }}</strong>
        </div>

        @if($variants->count() > 1)
            {{-- Color thumbnails --}}
            <div id="variantPicker" class="d-flex flex-wrap gap-2 mb-2">
                @foreach($thumbVariants as $colorName => $colorVars)
                    <button type="button"
                            class="btn p-0 border border-2 rounded {{ $loop->first ? 'border-primary' : 'border-light' }}"
                            style="width:64px;height:64px;overflow:hidden"
                            data-color="{{ $colorName }}"
                            title="{{ $colorName }}">
                        @if($thumbImgs[$colorName])
                            <img src="{{ $thumbImgs[$colorName] }}" style="width:100%;height:100%;object-fit:cover" alt="{{ $colorName }}">
                        @else
                            <span class="d-flex align-items-center justify-content-center w-100 h-100 bg-light text-muted" style="font-size:.65rem;line-height:1.1">{{ $colorName }}</span>
                        @endif
                    </button>
                @endforeach
            </div>

            {{-- Age + Size pickers (rendered by JS) --}}
            <div id="agePicker" class="d-flex flex-wrap gap-1 mb-2" style="display:none!important"></div>
            <div id="sizePicker" class="d-flex flex-wrap gap-1 mb-2" style="display:none!important"></div>
            {{-- Custom options pickers (rendered by JS) --}}
            <div id="customOptionPickers" class="d-flex flex-wrap flex-column gap-1 mb-2"></div>
        @endif

        @if($defaultVariant)
        <form id="addToCartForm" action="{{ route('shop.cart.add', $defaultVariant) }}" method="post" class="d-flex flex-wrap gap-2 align-items-center">
            @csrf
            <input type="number" name="quantity" value="{{ $remaining > 0 ? 1 : 0 }}" min="1"
                   max="{{ max($remaining, 1) }}" class="form-control" style="width:90px"
                   data-pdp="qty" @disabled($remaining <= 0)>
            <button class="btn {{ $remaining <= 0 ? 'btn-secondary' : 'btn-primary' }}"
                    data-pdp="add" @disabled($remaining <= 0)>
                <i class="bi bi-bag-plus"></i> Add to cart
            </button>
            <button type="button" data-pdp="remove"
                    class="btn btn-outline-danger {{ $inCart <= 0 ? 'd-none' : '' }}">
                <i class="bi bi-cart-dash"></i> Remove from cart
            </button>
            <small class="ms-2" data-pdp="stock">
                @if($stock <= 0)
                    <span class="text-danger">Out of stock</span>
                @elseif($remaining <= 0)
                    <span class="text-warning">All in cart</span>
                @else
                    <span class="text-success">In stock</span>
                @endif
            </small>
        </form>
        @else
            <div class="alert alert-light border small">This product has no variants yet. Please check back later.</div>
        @endif

        <div class="d-flex flex-wrap gap-3 mb-3 small text-muted align-items-center">
            @if((float) \App\Models\Setting::get('shipping_fee', 0) > 0)
                <span data-pdp="shipping-fee">
                    <i class="bi bi-truck me-1"></i>
                    Shipping: &#8358;{{ number_format((float) \App\Models\Setting::get('shipping_fee', 0), 2) }} per item
                </span>
            @endif
            <span><i class="bi bi-shield-check me-1"></i>Secure Checkout</span>
        </div>
    </div>
</div>

@if($variants->count() > 1)
@push('scripts')
<script>
(function () {
    const variantsData    = @json($variantsData);
    const cartUrlTemplate = @json(route('shop.cart.add', ['variant' => '__V__']));
    const fmt = n => '₦' + Number(n).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});

    window.pdpVariantsData = variantsData;

    // Current selection state
    let selectedColor = variantsData[0]?.color ?? null;
    let selectedAge   = null;
    let selectedSize  = null;

    // Detect custom option keys (anything in `options` that isn't color/age/size)
    const standardKeys = ['color', 'age', 'size', 'color_id', 'size_id', 'age_range_id'];
    const customOptionKeys = [];
    variantsData.forEach(v => {
        if (v.options && typeof v.options === 'object') {
            Object.keys(v.options).forEach(k => {
                if (!customOptionKeys.includes(k)) customOptionKeys.push(k);
            });
        }
    });
    const selectedCustom = {};
    customOptionKeys.forEach(k => { selectedCustom[k] = null; });

    // ── Helpers ─────────────────────────────────────────────────────────────
    function variantsForFilters(filters) {
        return variantsData.filter(v => {
            for (const [key, val] of Object.entries(filters)) {
                if (val === null || val === undefined || val === '') continue;
                if (key === 'color' && (v.color ?? null) !== val) return false;
                if (key === 'age' && (v.age ?? null) !== val) return false;
                if (key === 'size' && (v.size ?? null) !== val) return false;
                if (!standardKeys.includes(key) && (!v.options || v.options[key] !== val)) return false;
            }
            return true;
        });
    }

    function uniqueValues(variants, key) {
        if (key === 'color') return [...new Set(variants.map(v => v.color).filter(Boolean))];
        if (key === 'age')   return [...new Set(variants.map(v => v.age).filter(Boolean))];
        if (key === 'size')  return [...new Set(variants.map(v => v.size).filter(Boolean))];
        return [...new Set(variants.map(v => v.options?.[key]).filter(Boolean))];
    }

    function buildFilters() {
        const f = { color: selectedColor, age: selectedAge, size: selectedSize };
        customOptionKeys.forEach(k => { f[k] = selectedCustom[k]; });
        return f;
    }

    function findVariant(filters) {
        return variantsData.find(v => {
            if ((v.color ?? null) !== (filters.color ?? null)) return false;
            if ((v.age ?? null) !== (filters.age ?? null)) return false;
            if ((v.size ?? null) !== (filters.size ?? null)) return false;
            for (const k of customOptionKeys) {
                if ((v.options?.[k] ?? null) !== (filters[k] ?? null)) return false;
            }
            return true;
        }) ?? null;
    }

    // ── DOM refs ─────────────────────────────────────────────────────────────
    const form              = document.getElementById('addToCartForm');
    const agePickerEl       = document.getElementById('agePicker');
    const sizePickerEl      = document.getElementById('sizePicker');
    const customPickersEl   = document.getElementById('customOptionPickers');

    // ── Render pickers ───────────────────────────────────────────────────────
    function renderPills(container, items, active, onClick, label) {
        if (!container) return;
        container.innerHTML = '';
        if (!items.length) { container.style.display = 'none'; return; }
        container.style.display = '';

        const wrap = document.createElement('div');
        wrap.className = 'w-100 mb-1';
        const lbl = document.createElement('small');
        lbl.className = 'text-muted';
        lbl.textContent = label;
        wrap.appendChild(lbl);
        container.appendChild(wrap);

        items.forEach(item => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-sm rounded-pill ' + (item === active ? 'btn-primary text-white fw-bold border-0 shadow-sm' : 'btn-outline-secondary');
            btn.textContent = item;
            btn.addEventListener('click', () => onClick(item));
            container.appendChild(btn);
        });
    }

    function renderAgePicker() {
        const filtered = variantsForFilters({ color: selectedColor });
        const ages = uniqueValues(filtered, 'age');
        if (!selectedAge || !ages.includes(selectedAge)) selectedAge = ages[0] ?? null;
        renderPills(agePickerEl, ages, selectedAge, age => {
            selectedAge = age;
            selectedSize = null;
            customOptionKeys.forEach(k => { selectedCustom[k] = null; });
            renderDependentPickers();
            resolveAndSelect();
        }, 'Age range:');
    }

    function renderSizePicker() {
        const filtered = variantsForFilters({ color: selectedColor, age: selectedAge });
        const sizes = uniqueValues(filtered, 'size');
        if (!selectedSize || !sizes.includes(selectedSize)) selectedSize = sizes[0] ?? null;
        renderPills(sizePickerEl, sizes, selectedSize, size => {
            selectedSize = size;
            customOptionKeys.forEach(k => { selectedCustom[k] = null; });
            renderDependentPickers();
            resolveAndSelect();
        }, 'Size:');
    }

    function renderCustomPickers() {
        if (!customPickersEl) return;
        customPickersEl.innerHTML = '';

        const baseFilters = { color: selectedColor, age: selectedAge, size: selectedSize };

        customOptionKeys.forEach(key => {
            const filtered = variantsForFilters(baseFilters);
            const values = uniqueValues(filtered, key);
            if (!values.length) return;

            if (!selectedCustom[key] || !values.includes(selectedCustom[key])) {
                selectedCustom[key] = values[0] ?? null;
            }

            const row = document.createElement('div');
            row.className = 'd-flex flex-wrap gap-1 align-items-center';

            const label = document.createElement('small');
            label.className = 'text-muted me-2';
            label.textContent = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) + ':';
            row.appendChild(label);

            values.forEach(val => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-sm rounded-pill ' + (val === selectedCustom[key] ? 'btn-primary text-white fw-bold border-0 shadow-sm' : 'btn-outline-secondary');
                btn.textContent = val;
                btn.addEventListener('click', () => {
                    selectedCustom[key] = val;
                    renderCustomPickers();
                    resolveAndSelect();
                });
                row.appendChild(btn);
            });

            customPickersEl.appendChild(row);
        });
    }

    function renderDependentPickers() {
        renderAgePicker();
        renderSizePicker();
        renderCustomPickers();
    }

    function resolveAndSelect() {
        renderDependentPickers();

        const filters = buildFilters();
        const v = findVariant(filters)
            ?? variantsForFilters({ color: selectedColor, age: selectedAge })?.[0]
            ?? variantsForFilters({ color: selectedColor })?.[0]
            ?? variantsData[0];
        if (v) applyVariant(v);
    }

    // ── Apply a resolved variant to the page ─────────────────────────────────
    function applyVariant(v) {
        if (!form) return;
        form.action = cartUrlTemplate.replace('__V__', v.id);

        const priceNet   = document.querySelector('[data-pdp="price-net"]');
        const priceOld   = document.querySelector('[data-pdp="price-old"]');
        const priceBadge = document.querySelector('[data-pdp="price-badge"]');
        // SKU is intentionally not displayed on the product details page.

        if (v.discount > 0) {
            if (priceOld)   { priceOld.textContent = fmt(v.selling_price); priceOld.classList.remove('d-none'); }
            if (priceBadge) { priceBadge.textContent = '-' + (v.discount % 1 === 0 ? v.discount.toFixed(0) : v.discount.toFixed(2)) + '%'; priceBadge.classList.remove('d-none'); }
            if (priceNet)   { priceNet.textContent = fmt(v.net_price); priceNet.classList.add('text-danger', 'ms-2'); }
        } else {
            if (priceOld)   priceOld.classList.add('d-none');
            if (priceBadge) priceBadge.classList.add('d-none');
            if (priceNet)   { priceNet.textContent = fmt(v.selling_price); priceNet.classList.remove('text-danger', 'ms-2'); }
        }

        // Carousel
        const carouselInner = document.querySelector('#prod-carousel .carousel-inner');
        if (carouselInner && Array.isArray(v.images) && v.images.length) {
            carouselInner.innerHTML = v.images.map((url, i) =>
                '<div class="carousel-item' + (i === 0 ? ' active' : '') + '">' +
                '<img src="' + url + '" class="d-block w-100" style="aspect-ratio:1/1;object-fit:cover" alt="">' +
                '</div>'
            ).join('');
        }

        // Stock / qty UI
        if (typeof window.pdpApplyStockState === 'function') {
            window.pdpApplyStockState(v.in_cart || 0, v.stock);
        }

        // Highlight colour thumbnail
        document.querySelectorAll('#variantPicker [data-color]').forEach(b => {
            const isActive = b.dataset.color === (v.color ?? null);
            b.classList.toggle('border-primary', isActive);
            b.classList.toggle('border-light',   !isActive);
        });

        // Update the visible colour name label
        const colorRow  = document.querySelector('[data-pdp="color-row"]');
        const colorName = document.querySelector('[data-pdp="color-name"]');
        if (colorRow)  colorRow.style.display  = v.color ? '' : 'none';
        if (colorName) colorName.textContent = v.color || '';
    }

    // ── Thumbnail click ──────────────────────────────────────────────────────
    document.querySelectorAll('#variantPicker [data-color]').forEach(btn => {
        btn.addEventListener('click', () => {
            selectedColor = btn.dataset.color;
            selectedAge   = null;
            selectedSize  = null;
            resolveAndSelect();
        });
    });

    // ── Boot ─────────────────────────────────────────────────────────────────
    setTimeout(() => {
        const firstBtn = document.querySelector('#variantPicker [data-color]');
        if (firstBtn) firstBtn.click();
    }, 0);
})();
</script>
@endpush
@endif

<hr>

<h4 class="mb-3">Customer Reviews</h4>

@auth
    @if($canReview)
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-body">
            <h6>{{ $myReview ? 'Update your review' : 'Write a review' }}</h6>
            <form action="{{ route('shop.products.reviews.store', $product) }}" method="post">
                @csrf
                <div class="mb-2">
                    <label class="form-label small">Rating</label>
                    <select name="rating" class="form-select form-select-sm" style="max-width:150px">
                        @for($i=5; $i>=1; $i--)
                            <option value="{{ $i }}" @selected(($myReview?->rating ?? 5) === $i)>{{ $i }} star{{ $i>1?'s':'' }}</option>
                        @endfor
                    </select>
                </div>
                <div class="mb-2">
                    <input type="text" name="title" class="form-control" placeholder="Title (optional)" value="{{ $myReview?->title }}">
                </div>
                <div class="mb-2">
                    <textarea name="comment" class="form-control" rows="3" placeholder="Tell us about this product...">{{ $myReview?->comment }}</textarea>
                </div>
                <button class="btn btn-primary btn-sm">{{ $myReview ? 'Update review' : 'Submit review' }}</button>
            </form>
        </div>
    </div>
    @else
    <div class="alert alert-light border">
        You can only review products from delivered orders.
    </div>
    @endif
@else
    <div class="alert alert-light border">
        <a href="{{ route('shop.login') }}">Log in</a> to write a review.
    </div>
@endauth

<div class="row g-3">
    @forelse($product->reviews as $rev)
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="d-flex justify-content-between">
                    <strong>{{ $rev->customer->name }}</strong>
                    <span class="stars small">
                        @for($i=1; $i<=5; $i++)
                            <i class="bi bi-star{{ $i <= $rev->rating ? '-fill' : '' }}"></i>
                        @endfor
                    </span>
                </div>
                <small class="text-muted">{{ $rev->created_at->format('M d, Y') }}
                    @if($rev->verified_purchase) <span class="badge bg-success-subtle text-success">Verified Purchase</span> @endif
                </small>
                @if($rev->title)<div class="fw-semibold mt-1">{{ $rev->title }}</div>@endif
                @if($rev->comment)<p class="mb-0 mt-1">{{ $rev->comment }}</p>@endif
            </div></div>
        </div>
    @empty
        <div class="col-12 text-muted">No reviews yet. Be the first!</div>
    @endforelse
</div>

@if($related->count())
<hr class="my-5">
<h4 class="mb-3">Related Products</h4>
<div class="row g-3">
    @foreach($related as $r)
        <div class="col-6 col-md-3">@include('shop.partials.product-card', ['product' => $r])</div>
    @endforeach
</div>
@endif

@push('scripts')
<script>
(function () {
    const form      = document.getElementById('addToCartForm');
    const qtyEl     = form ? form.querySelector('[data-pdp="qty"]')    : null;
    const addBtn    = form ? form.querySelector('[data-pdp="add"]')    : null;
    const removeBtn = form ? form.querySelector('[data-pdp="remove"]') : null;
    const stockEl   = form ? form.querySelector('[data-pdp="stock"]')  : null;

    if (!form) return;

    // Cart quantities per variant id — seeded from server on page load.
    const cartQtys = @json((object) $cartQtys);

    // URL templates — __V__ is replaced with the real variant id at runtime.
    const removeUrlTemplate = @json(route('shop.cart.remove', ['variant' => '__V__']));

    // ── Navbar badge ────────────────────────────────────────────────────────
    function updateBadge(count) {
        const bag = document.querySelector('.navbar a[href*="cart"]');
        if (!bag) return;
        let b = bag.querySelector('.badge');
        if (count > 0) {
            if (!b) {
                b = document.createElement('span');
                b.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
                bag.appendChild(b);
            }
            b.textContent = count;
        } else if (b) {
            b.remove();
        }
    }

    // ── Stock label + button state ───────────────────────────────────────────
    // Called after every add / remove so the UI stays in sync.
    function applyStockState(inCart, stockQty) {
        const remaining = stockQty - inCart;

        if (stockEl) {
            if (stockQty <= 0) {
                stockEl.innerHTML = '<span class="text-danger">Out of stock</span>';
            } else if (remaining <= 0) {
                stockEl.innerHTML = '<span class="text-warning">All in cart</span>';
            } else {
                stockEl.innerHTML = '<span class="text-success">In stock</span>';
            }
        }

        const exhausted = stockQty <= 0 || remaining <= 0;

        // Add-to-cart button.
        if (addBtn) {
            addBtn.disabled = exhausted;
            addBtn.innerHTML = '<i class="bi bi-bag-plus"></i> Add to cart';
            if (exhausted) {
                addBtn.classList.replace('btn-primary', 'btn-secondary');
            } else {
                addBtn.classList.replace('btn-secondary', 'btn-primary');
            }
        }

        // Qty input.
        if (qtyEl) {
            if (exhausted) {
                qtyEl.value = 0;
                qtyEl.disabled = true;
            } else {
                qtyEl.disabled = false;
                qtyEl.max = remaining;
                const v = parseInt(qtyEl.value);
                if (v < 1 || v > remaining) qtyEl.value = 1;
            }
        }

        // Remove-from-cart button: visible whenever something is in the cart.
        if (removeBtn) {
            removeBtn.classList.toggle('d-none', inCart <= 0);
        }
    }

    // Expose so the variant-picker refresh() can call it.
    window.pdpApplyStockState = applyStockState;

    // ── Toast ────────────────────────────────────────────────────────────────
    function showToast(msg, ok) {
        let toast = document.getElementById('pdp-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'pdp-toast';
            toast.style.cssText = 'margin-top:.5rem;font-size:.875rem;transition:opacity .4s';
            form.insertAdjacentElement('afterend', toast);
        }
        toast.className = ok ? 'text-success' : 'text-danger';
        toast.textContent = msg;
        toast.style.opacity = '1';
        clearTimeout(toast._t);
        toast._t = setTimeout(() => { toast.style.opacity = '0'; }, 2500);
    }

    // Helper: extract variant id from the form action URL.
    function currentVariantId() {
        return parseInt(form.action.match(/\/(\d+)$/)?.[1]);
    }

    // ── Add to cart (AJAX POST) ──────────────────────────────────────────────
    form.addEventListener('submit', function (e) {
        e.preventDefault();

        if (addBtn) {
            addBtn.disabled = true;
            addBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        }

        fetch(form.action, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: new FormData(form),
        })
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                updateBadge(json.cart_count);
                const variantId = currentVariantId();
                if (variantId) cartQtys[variantId] = json.variant_qty;
                if (window.pdpVariantsData) {
                    const vd = window.pdpVariantsData.find(v => v.id === variantId);
                    if (vd) {
                        vd.in_cart = json.variant_qty;
                    }
                }
                applyStockState(json.variant_qty, json.variant_stock);
                showToast('✓ ' + json.message, true);
            } else {
                showToast(json.message || 'Could not add to cart.', false);
                if (addBtn) {
                    addBtn.disabled = false;
                    addBtn.innerHTML = '<i class="bi bi-bag-plus"></i> Add to cart';
                }
            }
        })
        .catch(() => {
            showToast('Something went wrong. Please try again.', false);
            if (addBtn) {
                addBtn.disabled = false;
                addBtn.innerHTML = '<i class="bi bi-bag-plus"></i> Add to cart';
            }
        });
    });

    // ── Remove from cart (AJAX DELETE) ──────────────────────────────────────
    if (removeBtn) {
        removeBtn.addEventListener('click', function () {
            removeBtn.disabled = true;
            removeBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

            const variantId = currentVariantId();
            const url   = removeUrlTemplate.replace('__V__', variantId);
            const token = form.querySelector('[name="_token"]')?.value;

            fetch(url, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token,
                },
            })
            .then(r => r.json())
            .then(json => {
                if (json.success) {
                    updateBadge(json.cart_count);
                    if (variantId) cartQtys[variantId] = 0;
                    if (window.pdpVariantsData) {
                        const vd = window.pdpVariantsData.find(v => v.id === variantId);
                        if (vd) vd.in_cart = 0;
                    }
                    // Restore button label BEFORE applyStockState hides it,
                    // so it is correct when shown again after the next add.
                    removeBtn.disabled = false;
                    removeBtn.innerHTML = '<i class="bi bi-cart-dash"></i> Remove from cart';
                    applyStockState(0, json.variant_stock);
                    showToast('Removed from cart.', true);
                } else {
                    removeBtn.disabled = false;
                    removeBtn.innerHTML = '<i class="bi bi-cart-dash"></i> Remove from cart';
                    showToast(json.message || 'Could not remove.', false);
                }
            })
            .catch(() => {
                removeBtn.disabled = false;
                removeBtn.innerHTML = '<i class="bi bi-cart-dash"></i> Remove from cart';
                showToast('Something went wrong.', false);
            });
        });
    }
})();
</script>
@endpush

@endsection
