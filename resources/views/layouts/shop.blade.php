@php($title = $title ?? 'Kids Store')
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} | Kids Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&family=Quicksand:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
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

        footer { background: linear-gradient(135deg,#1f2d3d,#3a1f5d); color:#e2d5f5; padding:2.5rem 0; margin-top:3rem; }
    </style>
    @stack('styles')
</head>
<body>
@php($user = auth()->user())
<nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top">
  <div class="container">
    <a class="navbar-brand text-primary" href="{{ route('shop.home') }}">
        <i class="bi bi-balloon-heart-fill"></i> Kids Store
    </a>
    <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="nav">
        <ul class="navbar-nav me-auto">
            <li class="nav-item"><a class="nav-link" href="{{ route('shop.home') }}">Home</a></li>
            <li class="nav-item"><a class="nav-link" href="{{ route('shop.products.index') }}">Shop</a></li>
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
                        <li><a class="dropdown-item" href="{{ route('shop.account.orders.index') }}">My Orders</a></li>
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

<main class="container py-4">
    @yield('content')
</main>

<footer>
    <div class="container small">
        &copy; {{ date('Y') }} Kids Store. All prices in &#8358; (NGN).
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
@include('partials.flash-alerts')
@stack('scripts')
</body>
</html>
