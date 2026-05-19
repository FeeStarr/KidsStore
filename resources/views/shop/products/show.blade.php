@extends('layouts.shop', ['title' => $product->name])
@section('content')

@php
    // Variants — only active ones are user-selectable.
    $product->loadMissing('variants.inventory', 'variants.image', 'variants.images', 'images');
    $variants = $product->variants->filter(fn ($v) => $v->is_active)->values();
    $defaultVariant = $variants->first() ?? $product->variants->first();

    // Option keys that represent sizes — these become pills, not thumbnails.
    $sizeOptionKeys = ['size'];

    // Build union of SIZE-type option keys → distinct values (these become selectable pills).
    $optionKeys = [];
    foreach ($variants as $v) {
        foreach ((array) $v->options as $k => $val) {
            if (in_array(strtolower((string) $k), $sizeOptionKeys)) {
                $optionKeys[$k][$val] = true;
            }
        }
    }
    $optionKeys = array_map(fn ($vals) => array_keys($vals), $optionKeys);
    $hasOptions = ! empty($optionKeys);

    // Current cart quantities per variant (keyed by variant id).
    $cartQtys = [];
    try {
        $cart = app(\App\Services\CartService::class);
        foreach ($variants as $v) {
            $q = $cart->getQty($v->id);
            if ($q > 0) $cartQtys[$v->id] = $q;
        }
    } catch (\Throwable $e) {}

    // Variant data for JS resolver
    $variantsData = $variants->map(function ($v) use ($product, $cartQtys) {
        $imgs = $v->images->isNotEmpty()
            ? $v->images->map(fn ($i) => $i->url)->all()
            : $product->images->map(fn ($i) => $i->url)->all();
        return [
            'id'            => $v->id,
            'sku'           => $v->sku,
            'options'       => (object) ((array) $v->options),
            'selling_price' => (float) $v->selling_price,
            'net_price'     => (float) $v->net_price,
            'discount'      => (float) $v->discount,
            'stock'         => (int) ($v->inventory->quantity ?? 0),
            'in_cart'       => (int) ($cartQtys[$v->id] ?? 0),
            'image_url'     => $v->image?->url,
            'images'        => $imgs,
            'options_label' => $v->options_label,
        ];
    })->values();

    // Group variants by their NON-size options (e.g. Color, Pattern) PLUS their image URL.
    // This ensures variants with different images always get separate thumbnails, even when
    // a product has no colour/style options (e.g. size-only products with per-size images).
    $thumbVariants = $variants->groupBy(function ($v) use ($sizeOptionKeys, $product) {
        $styleOpts = collect((array) $v->options)
            ->filter(fn ($val, $key) => ! in_array(strtolower((string) $key), $sizeOptionKeys))
            ->sortKeys()
            ->all();
        // Resolve this variant's representative image (same 3-level priority as $thumbMeta below)
        $imgUrl = $v->image?->url
               ?? $v->images->first()?->url
               ?? $product->images->first()?->url
               ?? '';
        return json_encode($styleOpts) . '|' . $imgUrl;
    })->map->values();

    // Pre-compute image URL + label for each thumbnail group (avoids @php inside @foreach).
    $thumbMeta = [];
    foreach ($thumbVariants as $groupKey => $groupVars) {
        $tImg = null;
        foreach ($groupVars as $gv) {
            // 1. Explicit primary image (image_id)
            if ($gv->image) { $tImg = $gv->image->url; break; }
            // 2. First gallery image assigned to this variant
            if ($gv->images->isNotEmpty()) { $tImg = $gv->images->first()->url; break; }
        }
        // 3. Fall back to the product's first image
        $tImg = $tImg ?? ($product->images->first()?->url ?? '');
        $tLabel = collect((array) $groupVars->first()->options)
            ->filter(fn ($val, $key) => ! in_array(strtolower((string) $key), $sizeOptionKeys))
            ->values()
            ->implode(' / ');
        if ($tLabel === '') $tLabel = $groupVars->first()->options_label;
        $thumbMeta[$groupKey] = ['img' => $tImg, 'label' => $tLabel];
    }

    $hasDiscount = (float) ($defaultVariant?->discount ?? 0) > 0;
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
        <div class="text-muted small mb-2">
            <span data-pdp="sku">SKU: {{ $defaultVariant?->sku ?? $product->sku }}</span>
            @if($product->brand) | Brand: {{ $product->brand }} @endif
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

        <div class="mb-3 small">
            @if(!empty($product->age_group)) <span class="badge text-bg-light" data-pdp="age">Age: {{ implode(', ', (array) $product->age_group) }}</span> @endif
            @if($product->gender)    <span class="badge text-bg-light">{{ ucfirst($product->gender) }}</span> @endif
        </div>

        @if($variants->count() > 1)
            <div id="variantPicker" class="d-flex flex-wrap gap-2 mb-2">
                @foreach($thumbVariants as $groupKey => $thumbVars)
                    <button type="button"
                            class="btn p-0 border border-2 rounded {{ $loop->first ? 'border-primary' : 'border-light' }}"
                            style="width:64px;height:64px;overflow:hidden"
                            data-variant-ids="{{ $thumbVars->pluck('id')->implode(',') }}"
                            title="{{ $thumbMeta[$groupKey]['label'] }}">
                        @if($thumbMeta[$groupKey]['img'])
                            <img src="{{ $thumbMeta[$groupKey]['img'] }}" style="width:100%;height:100%;object-fit:cover" alt="{{ $thumbMeta[$groupKey]['label'] }}">
                        @else
                            <span class="d-flex align-items-center justify-content-center w-100 h-100 bg-light text-muted" style="font-size:.65rem;line-height:1.1">{{ $thumbMeta[$groupKey]['label'] }}</span>
                        @endif
                    </button>
                @endforeach
            </div>

            @if($hasOptions)
            <div id="optionPicker" class="mb-3" style="display:none">
                @foreach($optionKeys as $key => $vals)
                <div class="mb-2">
                    <span class="small text-muted">{{ $key }}:</span>
                    <div class="d-flex flex-wrap gap-1 mt-1" data-opt-group="{{ $key }}">
                        @foreach($vals as $val)
                        <button type="button"
                                class="btn btn-sm btn-outline-secondary rounded-pill"
                                data-opt-key="{{ $key }}" data-opt-val="{{ $val }}">{{ $val }}</button>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        @endif

        <form id="addToCartForm" action="{{ $defaultVariant ? route('shop.cart.add', $defaultVariant) : '#' }}" method="post" class="d-flex flex-wrap gap-2 align-items-center">
            @csrf
            <input type="number" name="quantity" value="{{ $remaining > 0 ? 1 : 0 }}" min="1"
                   max="{{ max($remaining, 1) }}" class="form-control" style="width:90px"
                   data-pdp="qty" @disabled($remaining <= 0)>
            <button class="btn {{ $remaining <= 0 ? 'btn-secondary' : 'btn-primary' }}"
                    data-pdp="add" @disabled($remaining <= 0)>
                <i class="bi bi-bag-plus"></i> Add to cart
            </button>
            {{-- Show "Remove from cart" whenever something is already in the cart --}}
            <button type="button" data-pdp="remove"
                    class="btn btn-outline-danger {{ $inCart <= 0 ? 'd-none' : '' }}">
                <i class="bi bi-cart-dash"></i> Remove from cart
            </button>
            <small class="ms-2" data-pdp="stock">
                @if($stock <= 0)
                    <span class="text-danger">Out of stock</span>
                @elseif($remaining <= 0)
                    <span class="text-warning">All {{ $stock }} in cart</span>
                @elseif($inCart > 0)
                    <span class="text-success">{{ $remaining }} left</span>
                @else
                    <span class="text-success">{{ $stock }} in stock</span>
                @endif
            </small>
        </form>
    </div>
</div>

@if($variants->count() > 1)
@push('scripts')
<script>
(function () {
    const variantsData    = @json($variantsData);
    const cartUrlTemplate = @json(route('shop.cart.add', ['variant' => '__V__']));
    const sizeOptionKeys  = @json($sizeOptionKeys);
    const fmt = n => '₦' + Number(n).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});

    // Expose so the AJAX cart block can keep in_cart counts in sync.
    window.pdpVariantsData = variantsData;

    // IDs belonging to the currently selected thumbnail group.
    let groupIds = [];

    // Show only SIZE-type pills whose values exist in the current group; hide the rest.
    function filterPills(ids) {
        const avail = {};
        ids.forEach(id => {
            const vd = variantsData.find(v => v.id === id);
            if (!vd || !vd.options) return;
            Object.entries(vd.options).forEach(([k, val]) => {
                if (!sizeOptionKeys.includes(k.toLowerCase())) return; // only size dimensions as pills
                (avail[k] = avail[k] || new Set()).add(String(val));
            });
        });
        document.querySelectorAll('#optionPicker [data-opt-val]').forEach(btn => {
            const show = !!(avail[btn.dataset.optKey] && avail[btn.dataset.optKey].has(String(btn.dataset.optVal)));
            btn.classList.toggle('d-none', !show);
        });
        const picker = document.getElementById('optionPicker');
        if (picker) picker.style.display = Object.keys(avail).length ? '' : 'none';
    }

    function selectVariant(v) {
        const form       = document.getElementById('addToCartForm');
        const priceNet   = document.querySelector('[data-pdp="price-net"]');
        const priceOld   = document.querySelector('[data-pdp="price-old"]');
        const priceBadge = document.querySelector('[data-pdp="price-badge"]');
        const skuEl      = document.querySelector('[data-pdp="sku"]');

        form.action = cartUrlTemplate.replace('__V__', v.id);
        if (skuEl) skuEl.textContent = 'SKU: ' + v.sku;

        if (v.discount > 0) {
            if (priceOld)   { priceOld.textContent = fmt(v.selling_price); priceOld.classList.remove('d-none'); }
            if (priceBadge) { priceBadge.textContent = '-' + (v.discount % 1 === 0 ? v.discount.toFixed(0) : v.discount.toFixed(2)) + '%'; priceBadge.classList.remove('d-none'); }
            if (priceNet)   { priceNet.textContent = fmt(v.net_price); priceNet.classList.add('text-danger', 'ms-2'); }
        } else {
            if (priceOld)   priceOld.classList.add('d-none');
            if (priceBadge) priceBadge.classList.add('d-none');
            if (priceNet)   { priceNet.textContent = fmt(v.selling_price); priceNet.classList.remove('text-danger', 'ms-2'); }
        }

        // Swap carousel to this variant's images.
        const carouselInner = document.querySelector('#prod-carousel .carousel-inner');
        if (carouselInner && Array.isArray(v.images) && v.images.length) {
            carouselInner.innerHTML = v.images.map((url, i) =>
                '<div class="carousel-item' + (i === 0 ? ' active' : '') + '">' +
                '<img src="' + url + '" class="d-block w-100" style="aspect-ratio:1/1;object-fit:cover" alt="">' +
                '</div>'
            ).join('');
        }

        if (typeof window.pdpApplyStockState === 'function') {
            window.pdpApplyStockState(v.in_cart || 0, v.stock);
        }

        // Update age/size badge.
        const ageEl = document.querySelector('[data-pdp="age"]');
        if (ageEl && v.options_label && v.options_label !== 'Default') {
            ageEl.textContent = 'Age: ' + v.options_label;
            ageEl.classList.remove('d-none');
        }

        // Highlight the matching thumbnail (uses data-variant-ids).
        document.querySelectorAll('#variantPicker [data-variant-ids]').forEach(b => {
            const ids = b.dataset.variantIds.split(',').map(Number);
            b.classList.toggle('border-primary', ids.includes(v.id));
            b.classList.toggle('border-light',   !ids.includes(v.id));
        });

        // Highlight the matching pills (visible ones only).
        const opts = v.options || {};
        document.querySelectorAll('#optionPicker [data-opt-val]:not(.d-none)').forEach(b => {
            const active = opts[b.dataset.optKey] !== undefined &&
                           String(opts[b.dataset.optKey]) === String(b.dataset.optVal);
            b.classList.toggle('btn-secondary',      active);
            b.classList.toggle('text-white',         active);
            b.classList.toggle('btn-outline-secondary', !active);
        });
    }

    // Thumbnail click → filter pills to this group, then select first variant in group.
    document.querySelectorAll('#variantPicker [data-variant-ids]').forEach(btn => {
        btn.addEventListener('click', () => {
            groupIds = btn.dataset.variantIds.split(',').map(Number);
            filterPills(groupIds);
            const first = variantsData.find(v => v.id === groupIds[0]);
            if (first) selectVariant(first);
        });
    });

    // Option pill click → find the variant within the current group that matches.
    document.querySelectorAll('#optionPicker [data-opt-val]').forEach(btn => {
        btn.addEventListener('click', () => {
            if (btn.classList.contains('d-none')) return;
            // Collect currently active pills + this new selection.
            const sel = {};
            document.querySelectorAll('#optionPicker [data-opt-val].btn-secondary:not(.d-none)').forEach(b => {
                sel[b.dataset.optKey] = b.dataset.optVal;
            });
            sel[btn.dataset.optKey] = btn.dataset.optVal;
            const v = variantsData.find(vd =>
                groupIds.includes(vd.id) &&
                vd.options &&
                Object.entries(sel).every(([k, val]) => String(vd.options[k]) === String(val))
            );
            if (v) selectVariant(v);
        });
    });

    // Defer initialisation so the AJAX block's pdpApplyStockState is defined first.
    setTimeout(function () {
        const firstBtn = document.querySelector('#variantPicker [data-variant-ids]');
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

        // "N left" / "N in stock" / "Out of stock" label.
        if (stockEl) {
            if (stockQty <= 0) {
                stockEl.innerHTML = '<span class="text-danger">Out of stock</span>';
            } else if (remaining <= 0) {
                stockEl.innerHTML = '<span class="text-warning">All ' + stockQty + ' in cart</span>';
            } else if (inCart > 0) {
                stockEl.innerHTML = '<span class="text-success">' + remaining + ' left</span>';
            } else {
                stockEl.innerHTML = '<span class="text-success">' + stockQty + ' in stock</span>';
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
                    if (vd) vd.in_cart = json.variant_qty;
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
