@php($appName = \App\Models\Setting::get('app_name', config('app.name', 'KidsFlairr')))
@php($title = $title ?? $appName)
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} | {{ $appName }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&family=Quicksand:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <style>
        :root {
            --kid-pink:   #ff6fa3;
            --kid-yellow: #ffd166;
            --kid-blue:   #4cc9f0;
            --kid-green:  #06d6a0;
            --kid-purple: #9b5de5;
            --kid-orange: #ff8c42;
        }
        body {
            background:
                radial-gradient(circle at 10% 0%,  rgba(255,209,102,.25), transparent 40%),
                radial-gradient(circle at 90% 10%, rgba(76,201,240,.25),  transparent 45%),
                radial-gradient(circle at 50% 100%,rgba(255,111,163,.18), transparent 50%),
                #fffaf3;
            font-family: 'Quicksand', system-ui, sans-serif;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        h1, h2, h3, h4, h5, .navbar-brand, .display-1, .display-2, .display-3, .display-4 {
            font-family: 'Fredoka', system-ui, sans-serif;
            letter-spacing: .3px;
        }
        .navbar { backdrop-filter: blur(8px); background: rgba(255,255,255,.85) !important; }
        .navbar-brand { font-weight: 700; font-size: 1.5rem; }
        .navbar-brand i { color: var(--kid-pink); }
        .btn-primary { background: var(--kid-pink); border-color: var(--kid-pink); }
        .btn-primary:hover { background: #ff4f8e; border-color: #ff4f8e; }
        .btn-outline-primary { color: var(--kid-pink); border-color: var(--kid-pink); }
        .btn-outline-primary:hover { background: var(--kid-pink); border-color: var(--kid-pink); }
        .text-primary { color: var(--kid-pink) !important; }
        .product-card .img-wrap { aspect-ratio: 1/1; background:#fff; border-radius:1rem; overflow:hidden; box-shadow: 0 4px 20px rgba(0,0,0,.05); }
        .product-card .img-wrap img { width:100%; height:100%; object-fit:cover; transition: transform .3s ease; }
        .product-card:hover .img-wrap img { transform: scale(1.05); }
        .price-old { text-decoration: line-through; color:#aaa; font-size:.9rem; }
        .stars { color:#f5b301; letter-spacing:1px; }
        .hero {
            background: linear-gradient(120deg, var(--kid-pink), var(--kid-purple) 50%, var(--kid-blue));
            border-radius: 1.5rem;
            padding: 3.5rem 2rem;
            color:#fff;
            position: relative;
            overflow: hidden;
            box-shadow: 0 18px 40px rgba(155,93,229,.25);
        }
        .hero::before, .hero::after {
            content: ""; position: absolute; border-radius: 50%; opacity: .35;
        }
        .hero::before { width: 220px; height: 220px; background: var(--kid-yellow); top: -60px; right: -40px; }
        .hero::after  { width: 160px; height: 160px; background: var(--kid-green);  bottom: -40px; left: 10%; }
        .hero h1 { font-size: clamp(2rem, 4vw, 3.5rem); }
        .hero .btn-light { border-radius: 50px; padding: .75rem 1.75rem; font-weight: 600; color: var(--kid-purple); }
        .floaty { animation: float 4s ease-in-out infinite; display:inline-block; }
        @keyframes float { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }

        .kid-tile {
            border: 0; border-radius: 1.25rem; padding: 1.5rem 1rem; text-align:center;
            color:#fff; transition: transform .25s ease, box-shadow .25s ease;
            box-shadow: 0 8px 20px rgba(0,0,0,.08);
        }
        .kid-tile:hover { transform: translateY(-6px) rotate(-1deg); box-shadow: 0 14px 28px rgba(0,0,0,.15); }
        .kid-tile i { font-size: 2.5rem; }
        .kid-tile strong { display:block; margin-top:.5rem; font-family:'Fredoka',sans-serif; font-size:1.1rem; }
        .kid-tile small { color: rgba(255,255,255,.85); }
        .tile-pink   { background: linear-gradient(135deg,#ff6fa3,#ff8fb6); }
        .tile-yellow { background: linear-gradient(135deg,#ffb347,#ffd166); }
        .tile-blue   { background: linear-gradient(135deg,#4cc9f0,#7ad7f0); }
        .tile-green  { background: linear-gradient(135deg,#06d6a0,#5be4b8); }
        .tile-purple { background: linear-gradient(135deg,#9b5de5,#c084fc); }
        .tile-orange { background: linear-gradient(135deg,#ff8c42,#ffb47a); }

        .feature-strip {
            background:#fff; border-radius:1rem;
            box-shadow: 0 4px 18px rgba(0,0,0,.05);
        }
        .feature-strip .feat i {
            width: 48px; height: 48px; border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 1.4rem; color: #fff;
        }
        .section-title { display:inline-block; padding:.25rem .9rem; border-radius:50px; background:#fff; box-shadow:0 4px 12px rgba(0,0,0,.06); font-family:'Fredoka',sans-serif; }

        .promo-section {
            position: relative;
            border-radius: 1.5rem;
            padding: 2.25rem 1.25rem 2.5rem;
            background:
                radial-gradient(circle at 8% 12%,  rgba(255,255,255,.22), transparent 45%),
                radial-gradient(circle at 92% 85%, rgba(255,255,255,.18), transparent 40%),
                linear-gradient(120deg, #3a1f5d, #9b5de5 55%, #ff6fa3);
            box-shadow: 0 18px 40px rgba(155,93,229,.28);
            overflow: hidden;
        }
        .promo-section::before {
            content: "\2728"; position: absolute; font-size: 1.1rem; opacity:.5;
        }
        .promo-title {
            font-family:'Fredoka',sans-serif;
            font-size: 1.35rem; color:#fff; font-weight:600;
            display:inline-flex; align-items:center; gap:.5rem;
            background: rgba(255,255,255,.16);
            padding:.35rem 1.1rem; border-radius:50px;
            backdrop-filter: blur(4px);
        }
        .promo-sub { color: rgba(255,255,255,.8); font-size:.92rem; }
        .promo-ticket {
            position: relative;
            border-radius: 1.1rem;
            background:#fff;
            padding: 1.4rem 1.2rem 1.2rem;
            box-shadow: 0 12px 28px rgba(0,0,0,.18);
            overflow: hidden;
            height: 100%;
            display:flex; flex-direction:column;
            transition: transform .25s ease, box-shadow .25s ease;
        }
        .promo-ticket:hover { transform: translateY(-6px) rotate(-1deg); box-shadow: 0 18px 36px rgba(0,0,0,.22); }
        .promo-ticket::before, .promo-ticket::after {
            content:""; position:absolute; width:22px; height:22px; border-radius:50%;
            background: var(--promo-bg); left:50%; transform:translateX(-50%);
            z-index:2;
        }
        .promo-ticket::before { top:-11px; }
        .promo-ticket::after  { bottom:-11px; }
        .promo-ticket .promo-notch { position:absolute; top:0; bottom:0; width:14px; z-index:2; }
        .promo-ticket .promo-notch.left  { left:0; }
        .promo-ticket .promo-notch.right { right:0; }
        .promo-code-chip {
            display:inline-block;
            font-family:'Fredoka',sans-serif; font-weight:700; letter-spacing:2px;
            font-size:1.25rem;
            color:#3a1f5d;
            background: #fff3d6;
            border:2px dashed #f5a623;
            border-radius:.65rem;
            padding:.35rem .9rem;
        }
        .promo-copy-btn {
            border:1px solid #e3d7f2; background:#f6f0ff; color:#7c4dcc;
            border-radius:50px; padding:.25rem .8rem; font-size:.8rem; font-weight:600;
            transition: background .2s ease;
        }
        .promo-copy-btn:hover { background:#e9dcff; }
        .promo-save {
            position:absolute; top:0; right:0;
            background: var(--kid-green); color:#fff;
            font-family:'Fredoka',sans-serif; font-weight:600; font-size:.78rem;
            padding:.4rem 1rem; border-radius:0 0 0 1.1rem;
            box-shadow: 0 4px 10px rgba(0,0,0,.12);
        }
        .promo-min { font-size:.78rem; color:#8a8fa3; }

        .fill-row { display:flex; flex-wrap:wrap; }
        .fill-tile { flex:1 1 0; min-width:150px; }

        footer { background: linear-gradient(135deg,#1f2d3d,#3a1f5d); color:#e2d5f5; padding:2.5rem 0; margin-top:3rem; }
    </style>
    @stack('styles')
</head>
<body>
@php($user = auth()->user())
<nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top">
  <div class="container">
    <a class="navbar-brand text-primary" href="{{ route('shop.home') }}">
        <i class="bi bi-balloon-heart-fill"></i> {{ $appName }}
    </a>
    <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="nav">
        <ul class="navbar-nav me-auto">
            <li class="nav-item"><a class="nav-link" href="{{ route('shop.home') }}">Home</a></li>
            <li class="nav-item"><a class="nav-link" href="{{ route('shop.products.index') }}">Shop</a></li>
            <li class="nav-item"><a class="nav-link" href="{{ route('shop.deals.index') }}"><i class="bi bi-fire me-1"></i>Deals</a></li>
            <li class="nav-item"><a class="nav-link" href="{{ route('shop.custom-frock.index') }}"><i class="bi bi-stars me-1"></i>Custom Frock</a></li>
            <li class="nav-item"><a class="nav-link" href="{{ route('shop.about') }}">About</a></li>
            <li class="nav-item"><a class="nav-link" href="{{ route('shop.contact') }}">Contact</a></li>
        </ul>
        <form class="d-flex me-3" action="{{ route('shop.products.index') }}">
            <input class="form-control form-control-sm" type="search" name="q" placeholder="Search products..." value="{{ request('q') }}">
        </form>
        <ul class="navbar-nav align-items-lg-center">
            <li class="nav-item">
                <a class="nav-link position-relative" href="{{ route('shop.cart.index') }}">
                    <i class="bi bi-bag fs-5"></i>
                    @if(($cartCount ?? 0) > 0)
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">{{ $cartCount }}</span>
                    @endif
                </a>
            </li>
            @auth
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#">
                        <i class="bi bi-person-circle"></i> {{ $user->name }}
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        @if($user->hasAnyRole(['superadmin', 'admin', 'staff']))
                            <li><a class="dropdown-item" href="{{ route('admin.dashboard') }}">Admin Panel</a></li>
                            <li><hr class="dropdown-divider"></li>
                        @endif
                        <li><a class="dropdown-item" href="{{ route('shop.account.profile') }}">My Profile</a></li>
                        <li><a class="dropdown-item" href="{{ route('shop.account.orders.index') }}">My Orders</a></li>
                        <li><a class="dropdown-item" href="{{ route('shop.custom-frock.index') }}"><i class="bi bi-stars me-1"></i>Custom Frock</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form action="{{ route('shop.logout') }}" method="post">@csrf
                                <button class="dropdown-item">Logout</button>
                            </form>
                        </li>
                    </ul>
                </li>
            @else
                <li class="nav-item"><a class="nav-link" href="{{ route('shop.login') }}">Login</a></li>
                <li class="nav-item"><a class="btn btn-sm btn-primary ms-2" href="{{ route('shop.register') }}">Sign up</a></li>
            @endauth
        </ul>
    </div>
  </div>
</nav>

<main class="container py-4" style="flex: 1 0 auto;">
    @yield('content')
</main>

<footer style="flex-shrink: 0;">
    <div class="container small">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span>&copy; {{ date('Y') }} {{ $appName }}. All prices in &#8358; (NGN).</span>
            <div class="d-flex gap-3">
                <a href="{{ route('shop.contact') }}" class="text-decoration-none text-white-50">Contact Us</a>
                <a href="{{ route('shop.return-policy') }}" class="text-decoration-none text-white-50">Return Policy</a>
                <a href="{{ route('shop.privacy-policy') }}" class="text-decoration-none text-white-50">Privacy Policy</a>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
@include('partials.flash-alerts')
@stack('scripts')
</body>
</html>
