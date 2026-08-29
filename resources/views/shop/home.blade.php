@extends('layouts.shop', ['title' => 'Welcome'])
@section('content')

<div class="hero mb-4">
    <div class="row align-items-center position-relative" style="z-index:1;">
        <div class="col-md-7">
            <span class="badge bg-white text-dark mb-3 px-3 py-2" style="border-radius:50px;">
                <i class="bi bi-stars text-warning"></i> Fun for every age
            </span>
            <h1 class="fw-bold mb-3">Where little dreams<br><span style="color:var(--kid-yellow);">come to play!</span></h1>
            <p class="lead mb-4 opacity-90">Clothes, shoes & more - handpicked for happy kids and delivered to your door.</p>
            <a href="{{ route('shop.products.index') }}" class="btn btn-light btn-lg me-2">
                <i class="bi bi-bag-heart-fill"></i> Shop now
            </a>
            <a href="{{ route('shop.products.index', ['sort' => 'price_asc']) }}" class="btn btn-outline-light btn-lg" style="border-radius:50px;">
                <i class="bi bi-tag-fill"></i> See deals
            </a>
        </div>
        <div class="col-md-5 text-center d-none d-md-block">
            <span class="floaty" style="font-size:8rem;">&#127881;</span>
        </div>
    </div>
</div>

<div class="feature-strip p-3 p-md-4 mb-5">
    <div class="fill-row gap-3 text-center">
        <div class="feat fill-tile px-2">
            <i style="background:var(--kid-pink);"><i class="bi bi-truck"></i></i>
            <div class="mt-2"><strong>Fast Delivery</strong><br><small class="text-muted">Right to your door</small></div>
        </div>
        <div class="feat fill-tile px-2">
            <i style="background:var(--kid-blue);"><i class="bi bi-shield-check"></i></i>
            <div class="mt-2"><strong>Safe & Trusted</strong><br><small class="text-muted">Quality guaranteed</small></div>
        </div>
        <div class="feat fill-tile px-2">
            <i style="background:var(--kid-green);"><i class="bi bi-arrow-repeat"></i></i>
            <div class="mt-2"><strong>Returns Available</strong><br><small class="text-muted">Subject to our return policy</small></div>
        </div>
        <div class="feat fill-tile px-2">
            <i style="background:var(--kid-purple);"><i class="bi bi-emoji-smile"></i></i>
            <div class="mt-2"><strong>Kid Approved</strong><br><small class="text-muted">Smiles every time</small></div>
        </div>
    </div>
</div>

@if($coupons->count())
@php
    $promoPalette = [
        ['#ffd166', '#ff9f43', '#fff6e0'],   // sunshine
        ['#4cc9f0', '#3a86ff', '#e8f7ff'],   // sky
        ['#06d6a0', '#00b894', '#e6fbf3'],   // mint
        ['#ff6fa3', '#ff4f8e', '#fff0f6'],   // pink
        ['#c084fc', '#9b5de5', '#f6ecff'],   // grape
        ['#ff8c42', '#ff6b35', '#fff1e6'],   // peach
    ];
@endphp
<div class="promo-section mb-5">
    <div class="text-center mb-4">
        <div class="promo-title">
            <i class="bi bi-ticket-perforated-fill"></i> Today's Promo Codes
        </div>
        <div class="promo-sub mt-2">Snag these special codes &amp; save on your little one's favourites!</div>
    </div>
    <div class="fill-row gap-3">
        @foreach($coupons as $i => $c)
            @php
                [$light, $accent, $chipBg] = $promoPalette[$i % count($promoPalette)];
            @endphp
            <div class="fill-tile">
                <div class="promo-ticket" style="--promo-bg:{{ $light }};">
                    <span class="promo-notch left" style="background:{{ $light }};"></span>
                    <span class="promo-notch right" style="background:{{ $light }};"></span>
                    <span class="promo-save" style="background:{{ $accent }};">{{ $c->discount_label }}</span>
                    <div class="text-center mt-3">
                        <div class="small fw-semibold text-uppercase" style="color:{{ $accent }}; letter-spacing:1px;">{{ $c->name }}</div>
                        <div class="promo-code-chip mt-2 mb-2">{{ strtoupper($c->code) }}</div>
                        <div>
                            <button type="button" class="promo-copy-btn promo-home-copy" data-code="{{ $c->code }}">
                                <i class="bi bi-clipboard"></i> Copy code
                            </button>
                        </div>
                        @if($c->minimum_order_amount)
                            <div class="promo-min mt-3"><i class="bi bi-info-circle"></i> Min. order &#8358;{{ number_format($c->minimum_order_amount, 2) }}</div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

<script>
document.querySelectorAll('.promo-home-copy').forEach(btn => {
    btn.addEventListener('click', () => {
        const code = btn.dataset.code;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(code);
        } else {
            const ta = document.createElement('textarea');
            ta.value = code;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            ta.remove();
        }
        const original = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check2"></i> Copied!';
        setTimeout(() => { btn.innerHTML = original; }, 1500);
    });
});
</script>
@endif

@if($categories->count())
@php
    $tilePalette = ['tile-pink', 'tile-yellow', 'tile-blue', 'tile-green', 'tile-purple', 'tile-orange'];
    $iconMap = [
        'toys' => 'bi-puzzle-fill',
        'clothing' => 'bi-bag-heart-fill',
        'books' => 'bi-book-half',
        'shoes' => 'bi-handbag-fill',
        'accessories' => 'bi-gem',
    ];
@endphp
<div class="text-center mb-3">
    <span class="section-title fs-5"><i class="bi bi-grid-3x3-gap-fill text-warning"></i> Shop by Category</span>
</div>
<div class="fill-row gap-3 mb-5">
    @foreach($categories as $i => $c)
        @php
            $tile = $tilePalette[$i % count($tilePalette)];
            $slug = strtolower($c->slug ?? '');
            $icon = $iconMap[$slug] ?? 'bi-balloon-heart-fill';
        @endphp
        <div class="fill-tile">
            <a href="{{ route('shop.products.index', ['category' => $c->id]) }}" class="text-decoration-none h-100 d-block">
                <div class="kid-tile {{ $tile }} h-100">
                    <i class="bi {{ $icon }}"></i>
                    <strong>{{ $c->name }}</strong>
                </div>
            </a>
        </div>
    @endforeach
</div>
@endif

<div class="mb-5 position-relative overflow-hidden" style="background: linear-gradient(135deg, #ff6fa3 0%, #c77dff 38%, #7b68ee 68%, #4cc9f0 100%); border-radius:24px; padding:2.2rem 1.6rem;">
    {{-- floating doodles --}}
    <span class="position-absolute" style="top:-12px; right:12%; font-size:2.2rem; opacity:.28; transform:rotate(12deg);">✦</span>
    <span class="position-absolute" style="bottom:10px; right:6%; font-size:1.6rem; opacity:.22;">❤</span>
    <span class="position-absolute" style="top:18px; left:4%; font-size:1.3rem; opacity:.2;">✿</span>
    <div class="row align-items-center position-relative" style="z-index:1;">
        <div class="col-md-8 text-white">
            <div class="d-inline-flex align-items-center gap-2 mb-2 px-3 py-1 bg-white bg-opacity-25 rounded-pill" style="backdrop-filter:blur(6px); font-weight:700; font-size:.82rem; letter-spacing:.04em;">
                <span style="background:#fff; color:#c77dff; width:22px; height:22px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center;"><i class="bi bi-stars"></i></span>
                Custom Frock • Made just for her
            </div>
            <h3 class="fw-bold mb-2" style="font-size:1.65rem; line-height:1.2; color:#fff;">
                Pick your style &amp; colours. We bring your dream frock to life, from beautiful birthday dresses to special outfits made just for her. ✨
            </h3>
            <a href="{{ route('shop.custom-frock.create') }}" class="btn btn-light btn-lg px-4" style="border-radius:50px; font-weight:800; color:#7b2d8b; box-shadow:0 8px 22px rgba(0,0,0,.18);">
                <i class="bi bi-palette-fill me-1"></i> Make your request
                <i class="bi bi-arrow-right ms-1"></i>
            </a>
            <div class="mt-2 small" style="color:rgba(255,255,255,.78);"><i class="bi bi-check-circle-fill me-1"></i> No stock limits - made to order</div>
        </div>
        <div class="col-md-4 text-center d-none d-md-block">
            <div class="position-relative d-inline-block">
                <span class="position-absolute" style="top:-6px; right:-8px; background:#ffd166; color:#5a3a00; font-size:.7rem; font-weight:800; padding:4px 8px; border-radius:50px; transform:rotate(6deg); box-shadow:0 4px 10px rgba(0,0,0,.12);">✨ NEW</span>
                <div style="font-size:6.2rem; filter:drop-shadow(0 10px 18px rgba(0,0,0,.18)); line-height:1;">👗</div>
                <div class="mx-auto mt-2 d-flex justify-content-center gap-1">
                    <span style="width:14px; height:14px; border-radius:50%; background:#ffd166; border:2px solid rgba(255,255,255,.9);"></span>
                    <span style="width:14px; height:14px; border-radius:50%; background:#ff6fa3; border:2px solid rgba(255,255,255,.9);"></span>
                    <span style="width:14px; height:14px; border-radius:50%; background:#7b68ee; border:2px solid rgba(255,255,255,.9);"></span>
                    <span style="width:14px; height:14px; border-radius:50%; background:#4cc9f0; border:2px solid rgba(255,255,255,.9);"></span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <span class="section-title fs-5"><i class="bi bi-stars text-warning"></i> Latest Goodies</span>
    <a href="{{ route('shop.products.index') }}" class="btn btn-sm btn-outline-primary" style="border-radius:50px;">View all <i class="bi bi-arrow-right"></i></a>
</div>
<div class="row g-3 mb-5">
    @forelse($featured as $product)
        <div class="col-6 col-md-3">@include('shop.partials.product-card', ['product' => $product])</div>
    @empty
        <div class="col-12 text-center text-muted py-5">No products available yet.</div>
    @endforelse
</div>

<div class="hero mb-4" style="background: linear-gradient(120deg, var(--kid-yellow), var(--kid-orange)); padding: 2.5rem;">
    <div class="row align-items-center position-relative" style="z-index:1;">
        <div class="col-md-8">
            <h2 class="fw-bold mb-2" style="color:#3a1f5d;">Ready for playtime? <span class="floaty">&#129409;</span></h2>
            <p class="mb-0" style="color:#3a1f5d;">Sign up today and get exclusive deals on your favorite picks.</p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            @guest
                <a href="{{ route('shop.register') }}" class="btn btn-light btn-lg" style="border-radius:50px; color:var(--kid-purple); font-weight:600;">
                    <i class="bi bi-person-plus-fill"></i> Join the fun
                </a>
            @else
                <a href="{{ route('shop.products.index') }}" class="btn btn-light btn-lg" style="border-radius:50px; color:var(--kid-purple); font-weight:600;">
                    <i class="bi bi-bag-heart-fill"></i> Keep shopping
                </a>
            @endguest
        </div>
    </div>
</div>

@endsection

