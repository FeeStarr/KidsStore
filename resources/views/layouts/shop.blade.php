@php($appName = \App\Models\Setting::get('app_name', config('app.name', 'KidsFlairr')))
@php($title = $title ?? $appName)
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} | {{ $appName }}</title>
    <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png">
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#ff6fa3">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="KidsFlairr">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
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
        /* CSS fallback for when Bootstrap JS is unavailable on shared hosting */
        #pwa-install-modal { display: none; opacity: 0; }
        #pwa-install-modal.modal-open { display: block !important; opacity: 1; }
        #pwa-install-modal.fallback-show { display: block; opacity: 1; position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 1050; background: rgba(0,0,0,.5); }
        #pwa-install-modal.fallback-show .modal-dialog { position: fixed; top: 50%; transform: translateY(-50%); z-index: 1060; }
        .pwa-backdrop { position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 1050; background: rgba(0,0,0,.5); }
    </style>
    @stack('styles')
</head>
<body>
@php($user = auth()->user())
<nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top">
  <div class="container">
    <a class="navbar-brand" href="{{ route('shop.home') }}">
        <img src="{{ asset('images/logo.png') }}" alt="{{ $appName }}" height="40" onerror="this.style.display='none';this.nextElementSibling.style.display='inline'">
        <span style="display:none"><i class="bi bi-balloon-heart-fill text-primary"></i> {{ $appName }}</span>
    </a>
<button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
     <div class="d-lg-none">
         <button id="nav-pwa-install-wrap" class="btn btn-sm btn-outline-primary m-2" onclick="showInstallModal()" style="border-radius:50px;font-size:.8rem;">
             <i class="bi bi-phone"></i> Get the App
         </button>
     </div>
     <div class="collapse navbar-collapse" id="nav">
        <ul class="navbar-nav me-auto">
            <li class="nav-item"><a class="nav-link" href="{{ route('shop.home') }}">Home</a></li>
            <li class="nav-item"><a class="nav-link" href="{{ route('shop.products.index') }}">Shop</a></li>
            <li class="nav-item"><a class="nav-link" href="{{ route('shop.deals.index') }}"><i class="bi bi-fire me-1"></i>Deals</a></li>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">More</a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="{{ route('shop.about') }}">About</a></li>
                    <li><a class="dropdown-item" href="{{ route('shop.contact') }}">Contact</a></li>
                    <li><a class="dropdown-item" href="{{ route('shop.order.lookup') }}"><i class="bi bi-box-seam me-1"></i>Track Order</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="{{ route('shop.custom-frock.create') }}"><i class="bi bi-scissors me-1"></i>Custom Orders</a></li>
                </ul>
            </li>
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
                        <li><a class="dropdown-item" href="{{ route('shop.custom-frock.create') }}"><i class="bi bi-scissors me-1"></i>Custom Orders</a></li>
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

@auth
    @if(!Auth::user()->hasVerifiedEmail())
        <div class="bg-warning bg-opacity-10 border-bottom border-warning py-2">
            <div class="container text-center small">
                <i class="bi bi-exclamation-triangle text-warning me-1"></i>
                Please verify your email address. Check your inbox or
                <form action="{{ route('shop.verification.resend') }}" method="post" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-link btn-sm p-0 fw-semibold text-decoration-underline">resend verification email</button>.
                </form>
            </div>
        </div>
    @endif
@endauth

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
                <a href="/cookie-policy" class="text-decoration-none text-white-50" onclick="try{localStorage.removeItem('kidsflairr_cookies_accepted')}catch(e){};">Cookie Policy</a>
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
<div id="pwa-install-modal" class="modal fade" tabindex="-1" style="z-index:1060;">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content text-center p-4" style="border-radius:1.25rem;">
            <div class="mb-3"><img src="{{ asset('images/logo.png') }}" alt="KidsFlairr" style="max-height:60px;" onerror="this.outerHTML='<div style=\'font-size:3rem\'>🎈</div>'"></div>
            <h5 class="fw-bold mb-2">Install KidsFlairr</h5>
            <p class="text-muted small mb-1">Add to your home screen for faster shopping!</p>
            <div id="pwa-already-installed" class="alert alert-success py-2 small mb-2" style="display:none;">Already installed - open from your home screen.</div>
            <div id="pwa-install-android" style="display:none;">
                <div id="pwa-android-fallback" class="bg-light rounded-3 p-3 mb-2">
                    <small class="text-muted">
                        <strong>To install:</strong> Tap the <strong>3-dot menu</strong> <i class="bi bi-three-dots-vertical"></i> in your browser, then tap <strong>"Install app"</strong>
                    </small>
                </div>
            </div>
            <div id="pwa-install-ios" style="display:none;">
                <div class="bg-light rounded-3 p-3 mb-2">
                    <small class="text-muted">
                        <strong>How to install:</strong><br>
                        1. Tap the <strong>Share</strong> button <i class="bi bi-box-arrow-up"></i><br>
                        2. Scroll down → <strong>Add to Home Screen</strong><br>
                        3. Tap <strong>Add</strong>
                    </small>
                </div>
            </div>
            <button id="pwa-dismiss-btn" class="btn btn-link text-muted small">Not now</button>
        </div>
    </div>
</div>

<script>
(function() {
    if (window.__kidsflairrPwaInit) return;
    window.__kidsflairrPwaInit = true;

    var DISMISS_KEY = 'kidsflairr_pwa_dismissed_at';
    var INSTALLED_KEY = 'kidsflairr_pwa_installed';
    var SNOOZE_MS = 7 * 24 * 60 * 60 * 1000;
    var SUCCESS_SHOWN_SESS = 'kidsflairr_pwa_success_shown';

    var isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
    var isAndroid = /Android/.test(navigator.userAgent);
    var isMobile = isIOS || isAndroid || (window.innerWidth < 768);

    function isStandalone() {
        return window.matchMedia('(display-mode: standalone)').matches
            || window.matchMedia('(display-mode: fullscreen)').matches
            || window.navigator.standalone === true;
    }
    function isDesktopWidth() { return window.innerWidth >= 992; }

    function hideNav() {
        var nav = document.getElementById('nav-pwa-install-wrap');
        if (nav) nav.style.display = 'none';
    }
    function showNav() {
        var nav = document.getElementById('nav-pwa-install-wrap');
        if (nav && isMobile && !isStandalone()) nav.style.display = '';
    }

    var fallback = document.getElementById('pwa-android-fallback');

    function showAlready() {
        var modal = document.getElementById('pwa-install-modal');
        var alreadyEl2 = document.getElementById('pwa-already-installed');
        if (alreadyEl2) alreadyEl2.style.display = '';
        if (alreadyEl2) alreadyEl2.textContent = 'KidsFlairr is already installed.';
        var aDiv = document.getElementById('pwa-install-android');
        var iDiv = document.getElementById('pwa-install-ios');
        if (aDiv) aDiv.style.display = 'none';
        if (iDiv) iDiv.style.display = 'none';
        if (modal) {
            try {
                if (!bootstrap.Modal.getInstance(modal)) {
                    new bootstrap.Modal(modal).show();
                }
            } catch(e) {
                modal.style.display = 'block';
                modal.style.opacity = '1';
                document.body.classList.add('modal-open');
            }
        }
    }

    function hideModal() {
        var modal = document.getElementById('pwa-install-modal');
        if (!modal) return;
        try {
            var inst = bootstrap.Modal.getInstance(modal);
            if (inst) inst.hide();
        } catch(e) {}
        modal.style.display = 'none';
        modal.style.opacity = '';
        document.body.classList.remove('modal-open');
        setTimeout(function() {
            var bd = document.querySelector('.pwa-backdrop, .modal-backdrop');
            if (bd) bd.remove();
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
        }, 300);
    }

    function onInstalledConfirmed() {
        try {
            if (sessionStorage.getItem(SUCCESS_SHOWN_SESS) === '1') return;
            sessionStorage.setItem(SUCCESS_SHOWN_SESS, '1');
        } catch(e) {}
        try { localStorage.setItem(INSTALLED_KEY, '1'); } catch(e) {}
        hideModal();
        hideNav();
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: 'KidsFlairr has been installed successfully.',
                text: 'KidsFlairr is now on your home screen.',
                showConfirmButton: true,
                confirmButtonText: 'Open KidsFlairr',
                confirmButtonColor: '#d63384',
                showCancelButton: false,
                allowOutsideClick: false
            }).then(function(r) {
                if (r.isConfirmed) window.location.href = '/';
            });
        }
        try {
            fetch('/pwa/install', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || '' },
                body: JSON.stringify({ platform: isIOS ? 'ios' : 'android', browser: navigator.userAgent })
            });
        } catch(e) {}
    }

    // --- standalone is primary truth ---
    if (isStandalone()) {
        try { localStorage.setItem(INSTALLED_KEY, '1'); } catch(e) {}
        hideNav();
        var _modalAlready = document.getElementById('pwa-install-modal');
        if (_modalAlready) {
            document.getElementById('nav-pwa-install-wrap')?.addEventListener('click', function(e){ e.preventDefault(); showAlready(); });
        }
        return;
    }
    // clear stale installed flag if not standalone (uninstalled)
    try {
        if (localStorage.getItem(INSTALLED_KEY) === '1' && !isStandalone()) {
            localStorage.removeItem(INSTALLED_KEY);
        }
    } catch(e) {}

    // snooze check
    try {
        var ts = parseInt(localStorage.getItem(DISMISS_KEY) || '0', 10);
        if (ts) {
            if (Date.now() - ts < SNOOZE_MS) { hideNav(); return; }
            else localStorage.removeItem(DISMISS_KEY);
        }
    } catch(e) {}

    // mobile-only: never show CTA/popup on desktop
    if (!isMobile || isDesktopWidth()) { hideNav(); }
    else { showNav(); }

    // Nav Get the App click
    var navWrap = document.getElementById('nav-pwa-install-wrap');
    if (navWrap) {
        navWrap.addEventListener('click', function(e) {
            if (!isMobile || isStandalone() || isDesktopWidth()) { e.preventDefault(); return; }
        });
    }

    // Show modal after 3s only on mobile, not installed, not snoozed
    if (isMobile && !isDesktopWidth() && !isStandalone()) {
        setTimeout(function() {
            if (isStandalone()) { showAlready(); return; }
            try {
                var ts2 = parseInt(localStorage.getItem(DISMISS_KEY)||'0',10);
                if (ts2 && (Date.now()-ts2 < SNOOZE_MS)) return;
            } catch(e){}
            var modal = document.getElementById('pwa-install-modal');
            if (!modal) return;
            var androidDiv = document.getElementById('pwa-install-android');
            var iosDiv = document.getElementById('pwa-install-ios');
            if (isIOS) {
                if (iosDiv) iosDiv.style.display = 'block';
                if (androidDiv) androidDiv.style.display = 'none';
            } else {
                if (androidDiv) androidDiv.style.display = 'block';
                if (iosDiv) iosDiv.style.display = 'none';
            }
            if (isStandalone()) { showAlready(); hideNav(); return; }
            // Always show via CSS — no dependency on Bootstrap JS
            modal.style.display = 'block';
            modal.style.opacity = '1';
            document.body.classList.add('modal-open');
            var bd = document.createElement('div');
            bd.className = 'pwa-backdrop';
            document.body.appendChild(bd);
        }, 3000);
    } else {
        hideNav();
    }

    // Dismiss -> 7-day snooze
    var dismissBtn = document.getElementById('pwa-dismiss-btn');
    if (dismissBtn) {
        dismissBtn.addEventListener('click', function() {
            try { localStorage.setItem(DISMISS_KEY, String(Date.now())); } catch(e) {}
            hideModal();
            hideNav();
        });
    }

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js').then(function(reg) {
            console.log('[PWA] SW registered');
        }).catch(function(err) {
            console.error('[PWA] SW registration failed:', err);
        });
    }
})();

// Global helper for inline onclick
function showInstallModal() {
    var modal = document.getElementById('pwa-install-modal');
    if (!modal) return;
    var androidDiv = document.getElementById('pwa-install-android');
    var iosDiv = document.getElementById('pwa-install-ios');
    var fallback = document.getElementById('pwa-android-fallback');
    var isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
    if (isIOS) {
        if (iosDiv) iosDiv.style.display = 'block';
        if (androidDiv) androidDiv.style.display = 'none';
    } else {
        if (androidDiv) androidDiv.style.display = 'block';
        if (iosDiv) iosDiv.style.display = 'none';
    }
    if (fallback) fallback.style.display = '';
    try {
        var inst = bootstrap.Modal.getInstance(modal);
        if (inst) {
            inst.show();
        } else {
            new bootstrap.Modal(modal).show();
        }
    } catch(e) {
        // Bootstrap JS not loaded — show modal via CSS fallback
        modal.style.display = 'block';
        modal.style.opacity = '1';
        document.body.classList.add('modal-open');
        var bd = document.createElement('div');
        bd.className = 'pwa-backdrop';
        bd.style.zIndex = '1050';
        document.body.appendChild(bd);
    }
}
</script>

@if(!str_starts_with(request()->path(), 'admin'))
<div id="cookie-banner" style="display:none;position:fixed;bottom:0;left:0;right:0;z-index:9998;background:#1f2d3d;color:#e2d5f5;padding:1rem 1.5rem;box-shadow:0 -2px 10px rgba(0,0,0,.15);">
    <div class="container d-flex flex-wrap align-items-center justify-content-between gap-3" style="max-width:960px;">
        <p class="mb-0 small" style="flex:1;min-width:250px;">
            We use cookies to keep your shopping cart, login, and checkout working securely.
            By continuing to browse, you agree to our use of necessary cookies.
            <a href="/cookie-policy" class="text-warning text-decoration-underline">Learn more</a>
        </p>
        <button id="cookie-accept" class="btn btn-sm btn-warning text-dark fw-semibold" style="border-radius:50px;white-space:nowrap;">
            <i class="bi bi-check-lg me-1"></i> Accept
        </button>
    </div>
</div>
<script>
(function() {
    try {
        if (localStorage.getItem('kidsflairr_cookies_accepted') === '1') return;
    } catch(e) {}
    var banner = document.getElementById('cookie-banner');
    if (!banner) return;
    setTimeout(function() { banner.style.display = 'block'; }, 1500);
    document.getElementById('cookie-accept')?.addEventListener('click', function() {
        try { localStorage.setItem('kidsflairr_cookies_accepted', '1'); } catch(e) {}
        banner.style.transition = 'opacity 0.3s';
        banner.style.opacity = '0';
        setTimeout(function() { banner.style.display = 'none'; }, 300);
    });
})();
</script>
@endif
</body>
</html>
