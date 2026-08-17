@php($appName = \App\Models\Setting::get('app_name', config('app.name', 'KidsFlairr')))
@php($title = $title ?? 'Pickup Portal')
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} | {{ $appName }}</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎈</text></svg>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <style>
        body { background: #f0f4f8; font-family: system-ui, sans-serif; }
        .portal-header { background: #1f2d3d; color: #fff; padding: .85rem 1.5rem; }
        .portal-header .brand { font-size: 1.1rem; font-weight: 700; }
        .status-badge-ready { background: #ffc107; color: #000; }
        .status-badge-delivered { background: #198754; color: #fff; }
    </style>
    @stack('styles')
</head>
<body>
<div class="portal-header d-flex justify-content-between align-items-center mb-4">
    <div>
        <span class="brand"><i class="bi bi-geo-alt-fill me-2" style="color:#ffc107"></i>Pickup Station Portal</span>
        @if(session('portal_station_name'))
            <span class="ms-3 small opacity-75">{{ session('portal_station_name') }}</span>
        @endif
    </div>
    @if(session('portal_station_id'))
        <form action="{{ route('pickup-portal.logout') }}" method="post" class="d-inline">
            @csrf
            <button class="btn btn-sm btn-outline-light"><i class="bi bi-box-arrow-right me-1"></i>Logout</button>
        </form>
    @endif
</div>

<div class="container" style="max-width:900px">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @yield('content')
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
@stack('scripts')
</body>
</html>
