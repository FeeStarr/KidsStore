@php($appName = \App\Models\Setting::get('app_name', config('app.name', 'KidsFlairr')))
@php($title = $title ?? 'Delivery Portal')
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} | {{ $appName }}</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📦</text></svg>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        body { background: #f0f4f8; font-family: system-ui, sans-serif; }
        .portal-header { background: #1a2332; color: #fff; padding: .85rem 1.5rem; }
        .portal-header .brand { font-size: 1.1rem; font-weight: 700; }
        .stat-card { border-left: 4px solid; border-radius: .5rem; }
        .stat-card.assigned { border-color: #0d6efd; }
        .stat-card.received { border-color: #ffc107; }
        .stat-card.delivered { border-color: #198754; }
        .stat-card.issues { border-color: #dc3545; }
        .delivery-card { border-left: 4px solid; transition: transform .1s; }
        .delivery-card.assigned { border-color: #0d6efd; }
        .delivery-card.received { border-color: #ffc107; }
        .delivery-card.delivered { border-color: #198754; }
        .delivery-card.failed { border-color: #dc3545; }
        .btn-action { min-height: 48px; font-size: 1rem; font-weight: 600; }
    </style>
    @stack('styles')
</head>
<body>
<div class="portal-header d-flex justify-content-between align-items-center mb-4">
    <div>
        <span class="brand"><i class="bi bi-truck me-2" style="color:#ffc107"></i>KidsFlairr Delivery</span>
        @if(Auth::check() && Auth::user()->deliveryAgent)
            <span class="ms-3 small opacity-75">{{ Auth::user()->deliveryAgent->account_number }}</span>
        @endif
    </div>
    @if(Auth::check())
        <form action="{{ route('delivery-portal.logout') }}" method="post" class="d-inline">
            @csrf
            <button class="btn btn-sm btn-outline-light"><i class="bi bi-box-arrow-right me-1"></i>Logout</button>
        </form>
    @endif
</div>

@if(Auth::check() && Auth::user()->deliveryAgent)
@php($r = request()->route()->getName())
<div class="container" style="max-width:900px">
    <div class="d-flex gap-2 flex-wrap mb-3">
        <a href="{{ route('delivery-portal.dashboard') }}" class="btn btn-sm {{ str_starts_with($r, 'delivery-portal.dashboard') ? 'btn-dark' : 'btn-outline-dark' }}">
            <i class="bi bi-grid"></i> Dashboard
        </a>
        <a href="{{ route('delivery-portal.deliveries') }}" class="btn btn-sm {{ str_starts_with($r, 'delivery-portal.deliveries') ? 'btn-dark' : 'btn-outline-dark' }}">
            <i class="bi bi-truck"></i> Deliveries
        </a>
        <a href="{{ route('delivery-portal.profile') }}" class="btn btn-sm {{ str_starts_with($r, 'delivery-portal.profile') ? 'btn-dark' : 'btn-outline-dark' }}">
            <i class="bi bi-person"></i> Profile
        </a>
    </div>
@endif

<div class="container" style="max-width:900px">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning alert-dismissible fade show">{{ session('warning') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @yield('content')
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
@stack('scripts')
</body>
</html>
