@php($appName = \App\Models\Setting::get('app_name', config('app.name', 'KidsFlairr')))
@php($title = $title ?? 'Admin')
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} | {{ $appName }}</title>
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
        .price-old { text-decoration: line-through; color: #aaa; font-size: .9rem; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <nav class="col-md-2 sidebar p-0">
            <div class="brand"><i class="bi bi-balloon-heart-fill"></i> {{ $appName }}</div>
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
                    @if(auth()->user()->hasPermission('manage_deals'))
                        <a href="{{ route('admin.deals.index') }}" class="{{ str_starts_with($r ?? '', 'admin.deals') ? 'active':'' }}"><i class="bi bi-fire"></i> Deals</a>
                    @endif
                    @if(auth()->user()->hasPermission('manage_coupons'))
                        <a href="{{ route('admin.coupons.index') }}" class="{{ str_starts_with($r ?? '', 'admin.coupons') ? 'active':'' }}"><i class="bi bi-ticket-perforated"></i> Coupons</a>
                    @endif
                    <a href="{{ route('admin.categories.index') }}" class="{{ str_starts_with($r ?? '', 'admin.categories') ? 'active':'' }}"><i class="bi bi-tags"></i> Categories</a>
                    <a href="{{ route('admin.inventory.index') }}" class="{{ str_starts_with($r ?? '', 'admin.inventory') ? 'active':'' }}"><i class="bi bi-archive"></i> Inventory</a>
                    <a href="{{ route('admin.purchases.index') }}" class="{{ str_starts_with($r ?? '', 'admin.purchases') ? 'active':'' }}"><i class="bi bi-truck"></i> Purchases</a>
                    <a href="{{ route('admin.suppliers.index') }}" class="{{ str_starts_with($r ?? '', 'admin.suppliers') ? 'active':'' }}"><i class="bi bi-building"></i> Suppliers</a>
                    <a href="{{ route('admin.payment-methods.index') }}" class="{{ str_starts_with($r ?? '', 'admin.payment-methods') ? 'active':'' }}"><i class="bi bi-wallet2"></i> Payment Methods</a>
                    <a href="{{ route('admin.orders.index') }}" class="{{ str_starts_with($r ?? '', 'admin.orders') ? 'active':'' }}"><i class="bi bi-bag-check"></i> Orders</a>
                    <a href="{{ route('admin.pickup-stations.index') }}" class="{{ str_starts_with($r ?? '', 'admin.pickup-stations') ? 'active':'' }}"><i class="bi bi-geo-alt"></i> Pickup Stations</a>
                    <a href="{{ route('admin.pickup-payouts.index') }}" class="{{ str_starts_with($r ?? '', 'admin.pickup-payouts') ? 'active':'' }}"><i class="bi bi-cash-stack"></i> Pickup Payouts</a>
                    <a href="{{ route('admin.refunds.index') }}" class="{{ str_starts_with($r ?? '', 'admin.refunds') ? 'active':'' }}">
                        <i class="bi bi-arrow-counterclockwise"></i> Refunds
                        @php($pendingRefunds = \App\Models\RefundRequest::whereIn('status', ['requested', 'pending_review', 'awaiting_evidence'])->count())
                        @if($pendingRefunds > 0)
                            <span class="badge bg-danger ms-1">{{ $pendingRefunds }}</span>
                        @endif
                    </a>
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
                    <a href="{{ route('admin.return-policy.edit') }}" class="{{ $r==='admin.return-policy.edit' ? 'active':'' }}"><i class="bi bi-arrow-counterclockwise"></i> Return Policy</a>
                    <a href="{{ route('admin.privacy-policy.edit') }}" class="{{ $r==='admin.privacy-policy.edit' ? 'active':'' }}"><i class="bi bi-shield-lock"></i> Privacy Policy</a>
                    @if(auth()->user()->hasPermission('manage_settings'))
                        <a href="{{ route('admin.settings.edit') }}" class="{{ str_starts_with($r ?? '', 'admin.settings') ? 'active':'' }}"><i class="bi bi-gear"></i> Settings</a>
                        <a href="{{ route('admin.bank-accounts.index') }}" class="{{ str_starts_with($r ?? '', 'admin.bank-accounts') ? 'active':'' }}"><i class="bi bi-bank"></i> Bank Accounts</a>
                    @endif
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

@auth
<script>
(function () {
    const TIMEOUT_MS  = 20 * 60 * 1000; // 20 minutes — matches SESSION_LIFETIME
    const WARNING_MS  = 18 * 60 * 1000; // warn 2 minutes before
    const LOGOUT_URL  = '{{ route("admin.logout") }}';
    const CSRF        = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    let warnTimer, logoutTimer;

    function resetTimers() {
        clearTimeout(warnTimer);
        clearTimeout(logoutTimer);

        warnTimer = setTimeout(() => {
            let remaining = 120; // 2 minutes in seconds
            let timerInterval;

            Swal.fire({
                title: 'Session expiring soon',
                html: 'You will be logged out in <strong id="swal-countdown">2:00</strong> due to inactivity.<br><small class="text-muted">Move your mouse or click to stay logged in.</small>',
                icon: 'warning',
                showCancelButton: false,
                confirmButtonText: 'Stay logged in',
                confirmButtonColor: '#198754',
                allowOutsideClick: false,
                didOpen: () => {
                    timerInterval = setInterval(() => {
                        remaining--;
                        const m = Math.floor(remaining / 60);
                        const s = remaining % 60;
                        const el = document.getElementById('swal-countdown');
                        if (el) el.textContent = m + ':' + String(s).padStart(2, '0');
                        if (remaining <= 0) clearInterval(timerInterval);
                    }, 1000);
                },
                willClose: () => clearInterval(timerInterval),
            }).then(result => {
                if (result.isConfirmed) resetTimers();
            });
        }, WARNING_MS);

        logoutTimer = setTimeout(() => {
            Swal.close();
            // POST to logout
            const form = document.createElement('form');
            form.method = 'post';
            form.action = LOGOUT_URL;
            const inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = '_token'; inp.value = CSRF;
            form.appendChild(inp);
            document.body.appendChild(form);
            form.submit();
        }, TIMEOUT_MS);
    }

    // Reset on any user interaction
    ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'].forEach(evt => {
        document.addEventListener(evt, resetTimers, { passive: true });
    });

    resetTimers(); // start on page load
})();
</script>
@endauth
</body>
</html>
