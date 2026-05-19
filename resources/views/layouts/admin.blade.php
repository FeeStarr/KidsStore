@php($title = $title ?? 'Admin')
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} | Kids Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/3.2.0/css/buttons.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    @stack('styles')
    <style>
        body { background: #f6f8fb; }
        .sidebar { min-height: 100vh; background: #1f2d3d; color: #cbd5e1; }
        .sidebar a { color: #cbd5e1; text-decoration: none; display: block; padding: .65rem 1rem; border-radius: .35rem; }
        .sidebar a.active, .sidebar a:hover { background: #2c3e50; color: #fff; }
        .brand { color:#fff; font-weight: 700; padding: 1rem; font-size: 1.2rem; }
        .stat-card { border-radius: .75rem; }
        /* DataTables export buttons spacing + ensure Bootstrap colors win */
        div.dt-buttons { margin-bottom: .75rem; }
        div.dt-buttons .btn { margin-right: .35rem; color: #fff !important; }
        div.dt-buttons .btn:hover { color: #fff !important; opacity: .92; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <nav class="col-md-2 sidebar p-0">
            <div class="brand"><i class="bi bi-balloon-heart-fill"></i> Kids Store</div>
            @if(auth()->check())
                <div class="px-3 py-2 bg-light border-bottom">
                    <small class="text-muted d-block">Logged in as:</small>
                    <strong>{{ auth()->user()->name }}</strong><br>
                    <small class="text-capitalize">
                        {{ implode(', ', auth()->user()->roles->pluck('name')->all() ?: [auth()->user()->role]) }}
                    </small>
                </div>
                <div class="px-2">
                    @php($r = request()->route()?->getName())
                    <a href="{{ route('admin.dashboard') }}" class="{{ $r==='admin.dashboard' ? 'active':'' }}"><i class="bi bi-speedometer2"></i> Dashboard</a>
                    <a href="{{ route('admin.products.index') }}" class="{{ str_starts_with($r ?? '', 'admin.products') ? 'active':'' }}"><i class="bi bi-box-seam"></i> Products</a>
                    <a href="{{ route('admin.categories.index') }}" class="{{ str_starts_with($r ?? '', 'admin.categories') ? 'active':'' }}"><i class="bi bi-tags"></i> Categories</a>
                    <a href="{{ route('admin.inventory.index') }}" class="{{ str_starts_with($r ?? '', 'admin.inventory') ? 'active':'' }}"><i class="bi bi-archive"></i> Inventory</a>
                    <a href="{{ route('admin.purchases.index') }}" class="{{ str_starts_with($r ?? '', 'admin.purchases') ? 'active':'' }}"><i class="bi bi-truck"></i> Purchases</a>
                    <a href="{{ route('admin.orders.index') }}" class="{{ str_starts_with($r ?? '', 'admin.orders') ? 'active':'' }}"><i class="bi bi-bag-check"></i> Orders</a>
                    <a href="{{ route('admin.users.index') }}" class="{{ str_starts_with($r ?? '', 'admin.users') ? 'active':'' }}"><i class="bi bi-people"></i> Users</a>
                    @if(auth()->user()->hasRole(\App\Models\User::ROLE_SUPERADMIN))
                        <a href="{{ route('admin.reports.profit') }}" class="{{ str_starts_with($r ?? '', 'admin.reports') ? 'active':'' }}"><i class="bi bi-graph-up-arrow"></i> Profit Report</a>
                    @endif
                    <a href="{{ route('admin.about.edit') }}" class="{{ $r==='admin.about.edit' ? 'active':'' }}"><i class="bi bi-info-circle"></i> About Page</a>
                    <a href="{{ route('admin.contact.edit') }}" class="{{ str_starts_with($r ?? '', 'admin.contact') ? 'active':'' }}">
                        <i class="bi bi-envelope"></i> Contact Page
                        @php($unreadCount = \App\Models\ContactMessage::where('read', false)->count())
                        @if($unreadCount > 0)
                            <span class="badge bg-danger ms-1">{{ $unreadCount }}</span>
                        @endif
                    </a>
                    <hr class="my-3">
                    <form method="post" action="{{ route('admin.logout') }}" class="px-2">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger w-100">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </button>
                    </form>
                </div>
            @endif
        </nav>
        <main class="@if(auth()->check()) col-md-10 @else col-md-12 @endif py-4 px-4">
            @yield('content')
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/pdfmake@0.2.10/build/pdfmake.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/pdfmake@0.2.10/build/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/3.2.0/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/3.2.0/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/3.2.0/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/3.2.0/js/buttons.print.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
@include('partials.flash-alerts')
@stack('scripts')
</body>
</html>
