@extends('layouts.pickup-portal', ['title' => 'Orders — '.session('portal_station_name')])
@section('content')

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">
        <i class="bi bi-box-seam me-2"></i>
        Orders — {{ session('portal_station_name') }}
    </h4>
    <div class="d-flex gap-2">
        <a href="{{ route('pickup-portal.dashboard', ['filter' => 'ready']) }}"
           class="btn btn-sm {{ $filter === 'ready' ? 'btn-warning' : 'btn-outline-secondary' }}">
            Awaiting Pickup
            @if($readyCount) <span class="badge bg-dark ms-1">{{ $readyCount }}</span> @endif
        </a>
        <a href="{{ route('pickup-portal.dashboard', ['filter' => 'all']) }}"
           class="btn btn-sm {{ $filter === 'all' ? 'btn-dark' : 'btn-outline-secondary' }}">
            All Orders
        </a>
        <a href="{{ route('pickup-portal.payouts') }}" class="btn btn-sm btn-outline-secondary">
            Payouts @if(isset($payoutCount) && $payoutCount > 0)<span class="badge bg-dark ms-1">{{ $payoutCount }}</span>@endif
        </a>
    </div>
</div>

@if($filter === 'all')
    <div class="card mb-3">
        <div class="card-body p-3">
            <div id="orders-error" class="alert alert-warning d-none" role="alert"></div>
            <div class="row g-2 mb-2 align-items-center">
                <div class="col-auto">
                    <select id="orders-filter-status" class="form-select form-select-sm" aria-label="Filter orders by status" title="Filter orders by status">
                        <option value="all">All statuses</option>
                        <option value="ready for pick up">Awaiting pickup</option>
                        <option value="delivered">Collected</option>
                        <option value="processing">Processing</option>
                    </select>
                </div>
                <div class="col-auto">
                    <select id="orders-filter-payment" class="form-select form-select-sm" aria-label="Filter orders by payment status" title="Filter orders by payment status">
                        <option value="all">Any payment</option>
                        <option value="paid">Paid</option>
                        <option value="pending">Pending</option>
                    </select>
                </div>
                <div class="col-auto">
                    <input id="orders-filter-from" type="date" class="form-control form-control-sm" placeholder="From" aria-label="Filter orders from date" title="Filter orders from date" />
                </div>
                <div class="col-auto">
                    <input id="orders-filter-to" type="date" class="form-control form-control-sm" placeholder="To" aria-label="Filter orders to date" title="Filter orders to date" />
                </div>
                <div class="col-auto">
                    <div class="btn-group" role="group">
                        <button id="orders-filter-apply" class="btn btn-sm btn-primary" aria-label="Apply filters" title="Apply filters">Filter</button>
                        <button id="orders-filter-clear" class="btn btn-sm btn-outline-secondary" aria-label="Clear filters" title="Clear filters">Clear</button>
                    </div>
                </div>
            </div>
            <div class="p-0">
            <table id="orders-table" class="table table-sm mb-0" style="width:100%">
                <thead>
                    <tr><th>#</th><th>Reference</th><th>Date</th><th>Customer</th><th class="text-end">Amount</th><th class="text-center">Status</th><th>Payment</th><th></th></tr>
                </thead>
                <tbody></tbody>
            </table>
            </div>
        </div>
    </div>
@else
    @if($orders->isEmpty())
        <div class="alert alert-light text-center py-4">
            <i class="bi bi-inbox fs-2 d-block mb-2 text-muted"></i>
            No {{ $filter === 'ready' ? 'orders awaiting pickup' : 'orders' }} at this station.
        </div>
    @else
        @foreach($orders as $order)
            @php
                $isReady  = $order->status === 'ready for pick up';
                $isDone   = $order->status === 'delivered';
                $isPaid   = $order->payment_status === 'paid';
                $balance  = (float) $order->balance;
            @endphp
            <div id="order-{{ $order->id }}" class="card mb-3 border-0 shadow-sm {{ $isReady ? 'border-start border-4 border-warning' : '' }}">
                <div class="card-body">
                    <div class="row align-items-start g-3">
                        {{-- Left: order info --}}
                        <div class="col-md-5">
                            <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                <span class="fw-bold fs-5">{{ $order->reference }}</span>
                                @if($isReady)
                                    <span class="badge status-badge-ready">Awaiting Pickup</span>
                                @elseif($isDone)
                                    <span class="badge status-badge-delivered">Collected</span>
                                @else
                                    <span class="badge bg-secondary">{{ $order->getStatusLabel() }}</span>
                                @endif
                                @if($isPaid)
                                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Paid</span>
                                @elseif($balance > 0)
                                    <span class="badge bg-danger"><i class="bi bi-exclamation-circle me-1"></i>Balance ₦{{ number_format($balance, 2) }}</span>
                                @endif
                            </div>
                            <div class="small text-muted">
                                <i class="bi bi-calendar me-1"></i>Ordered {{ $order->order_date->format('M d, Y') }}
                            </div>
                            @if($order->expected_delivery_date)
                                <div class="small {{ $isReady ? 'text-warning fw-semibold' : 'text-muted' }}">
                                    <i class="bi bi-clock me-1"></i>Expected {{ $order->expected_delivery_date->format('M d, Y') }}
                                </div>
                            @endif
                        </div>

                        {{-- Middle: customer --}}
                        <div class="col-md-3">
                            <div class="small text-muted mb-1">Customer</div>
                            <div class="fw-semibold">{{ $order->customer?->name ?? '—' }}</div>
                            <div class="small text-muted">{{ $order->customer?->phone ?? '' }}</div>
                            <div class="small text-muted">{{ $order->customer?->email ?? '' }}</div>
                        </div>

                        {{-- Right: items summary + action --}}
                        <div class="col-md-4 text-md-end">
                            <div class="small text-muted mb-1">Items</div>
                            @foreach($order->items->take(3) as $it)
                                <div class="small">
                                    {{ $it->product?->name }}
                                    @if($it->variant?->options_label) — <span class="text-muted">{{ $it->variant->options_label }}</span>@endif
                                    × {{ $it->quantity }}
                                </div>
                            @endforeach
                            @if($order->items->count() > 3)
                                <div class="small text-muted">+{{ $order->items->count() - 3 }} more</div>
                            @endif
                            <div class="fw-bold mt-1">₦{{ number_format($order->grand_total, 2) }}</div>

                            @if($isReady)
                                @if($isPaid || $balance <= 0)
                                    <button type="button" class="btn btn-success btn-sm mt-2 confirm-pickup-btn"
                                            data-order="{{ $order->reference }}"
                                            data-action="{{ route('pickup-portal.confirm', $order) }}">
                                        <i class="bi bi-check2-circle me-1"></i>Confirm Pickup
                                    </button>
                                @else
                                    <div class="mt-2">
                                        <button type="button"
                                                class="btn btn-warning btn-sm collect-payment-btn"
                                                data-order-id="{{ $order->id }}"
                                                data-initiate-url="{{ route('pickup-portal.initiate-payment', $order) }}"
                                                data-query-url="{{ route('pickup-portal.query-payment', $order) }}">
                                            <i class="bi bi-bank me-1"></i>Collect Payment
                                        </button>
                                        <div class="small text-danger mt-1">
                                            <i class="bi bi-lock me-1"></i>Payment pending — confirm after payment
                                        </div>
                                    </div>
                                    {{-- Inline payment panel (hidden until agent clicks Collect Payment) --}}
                                    <div class="payment-panel mt-2 p-2 bg-light rounded border" id="pay-panel-{{ $order->id }}" style="display:none">
                                        <div class="payment-loading text-muted small"><span class="spinner-border spinner-border-sm me-1"></span>Generating virtual account…</div>
                                        <div class="payment-account" style="display:none">
                                            <div class="small text-muted mb-1">Ask customer to transfer exactly:</div>
                                            <div class="d-flex gap-2 mb-2 flex-wrap">
                                                <div class="bg-white rounded border px-3 py-2 text-center flex-grow-1">
                                                    <div class="small text-muted">Bank</div>
                                                    <div class="fw-bold bank-name"></div>
                                                </div>
                                                <div class="bg-white rounded border px-3 py-2 text-center flex-grow-1">
                                                    <div class="small text-muted">Account No.</div>
                                                    <div class="fw-bold fs-5 font-monospace acct-no"></div>
                                                </div>
                                                <div class="bg-white rounded border px-3 py-2 text-center flex-grow-1">
                                                    <div class="small text-muted">Amount</div>
                                                    <div class="fw-bold text-primary acct-amount"></div>
                                                </div>
                                            </div>
                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                <span class="badge bg-warning text-dark">Expires in <span class="countdown-el">—</span></span>
                                                <button type="button" class="btn btn-sm btn-outline-success check-pay-btn">
                                                    <i class="bi bi-arrow-clockwise me-1"></i>Check payment
                                                </button>
                                                <span class="check-status small text-muted"></span>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <div class="small text-muted mb-1">Or ask the customer to transfer to one of these accounts:</div>
                                            @if(isset($bankAccounts) && $bankAccounts->isNotEmpty())
                                                <div class="list-group small">
                                                    @foreach($bankAccounts as $ba)
                                                        <div class="list-group-item d-flex justify-content-between align-items-start">
                                                            <div>
                                                                @if($ba->bank_name)<div><strong>{{ $ba->bank_name }}</strong></div>@endif
                                                                @if($ba->bank_account_name)<div class="small text-muted">{{ $ba->bank_account_name }}</div>@endif
                                                                <div class="font-monospace fw-semibold">{{ $ba->bank_account_number }}</div>
                                                                @if($ba->instructions)<div class="small text-muted">{{ $ba->instructions }}</div>@endif
                                                            </div>
                                                            <div class="text-end">
                                                                @if($ba->is_default)
                                                                    <span class="badge bg-primary small">Default</span>
                                                                @endif
                                                                <button class="btn btn-sm btn-outline-secondary ms-2" onclick="navigator.clipboard.writeText('{{ $ba->bank_account_number }}')">Copy</button>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @else
                                                <div class="small text-muted">No bank accounts configured.</div>
                                            @endif
                                        </div>
                                        <div class="payment-success alert alert-success py-2 mt-2 mb-0 small" style="display:none">
                                            <i class="bi bi-check-circle me-1"></i><strong>Payment received!</strong>
                                            Reload the page — the Confirm Pickup button will appear.
                                        </div>
                                    </div>
                                    {{-- After payment, allow confirm --}}
                                    <button type="button" class="btn btn-success btn-sm mt-2 confirm-pickup-btn paid-confirm d-none"
                                            data-order="{{ $order->reference }}"
                                            data-action="{{ route('pickup-portal.confirm', $order) }}">
                                        <i class="bi bi-check2-circle me-1"></i>Confirm Pickup
                                    </button>
                                @endif
                            @endif
                        </div>
                    </div>

                    {{-- Items detail (collapsible) --}}
                    <div class="mt-2">
                        <button class="btn btn-link btn-sm p-0 text-muted" type="button"
                                data-bs-toggle="collapse" data-bs-target="#items-{{ $order->id }}">
                            <i class="bi bi-list-ul me-1"></i>View full order
                        </button>
                        <div class="collapse mt-2" id="items-{{ $order->id }}">
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="table-light">
                                    <tr><th>Product</th><th>Variant</th><th>Qty</th><th class="text-end">Price</th></tr>
                                </thead>
                                <tbody>
                                    @foreach($order->items as $it)
                                        <tr>
                                            <td>{{ $it->product?->name }}</td>
                                            <td class="text-muted small">{{ $it->variant?->options_label }}</td>
                                            <td>{{ $it->quantity }}</td>
                                            <td class="text-end">₦{{ number_format($it->line_total, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="fw-bold">
                                        <td colspan="3" class="text-end">Total</td>
                                        <td class="text-end">₦{{ number_format($order->grand_total, 2) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                            <div class="small text-muted mt-1">
                                Payment: {{ ucfirst($order->payment_status) }}
                                @if((float)$order->balance > 0)
                                    <span class="text-danger ms-2">Balance due: ₦{{ number_format($order->balance, 2) }}</span>
                                @else
                                    <span class="text-success ms-2">Fully paid</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    @endif
@endif

@push('scripts')
<script>
(function () {
    // Highlight order if requested via query param
    const highlightId = {{ json_encode($highlightOrderId ?? null) }};
    if (highlightId) {
        window.addEventListener('DOMContentLoaded', () => {
            const el = document.getElementById('order-' + highlightId);
            if (el) {
                el.scrollIntoView({behavior: 'smooth', block: 'center'});
                el.classList.add('border', 'border-info');
                setTimeout(() => el.classList.remove('border', 'border-info'), 5000);
            }
        });
    }
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    // ── Collect Payment buttons ───────────────────────────────────────────────
    document.querySelectorAll('.collect-payment-btn').forEach(btn => {
        const orderId     = btn.dataset.orderId;
        const initiateUrl = btn.dataset.initiateUrl;
        const queryUrl    = btn.dataset.queryUrl;
        const panel       = document.getElementById('pay-panel-' + orderId);
        if (!panel) return;

        const loading   = panel.querySelector('.payment-loading');
        const acctBlock = panel.querySelector('.payment-account');
        const successEl = panel.querySelector('.payment-success');
        const bankName  = panel.querySelector('.bank-name');
        const acctNo    = panel.querySelector('.acct-no');
        const acctAmt   = panel.querySelector('.acct-amount');
        const cdEl      = panel.querySelector('.countdown-el');
        const checkBtn  = panel.querySelector('.check-pay-btn');
        const checkSt   = panel.querySelector('.check-status');

        let countdownSeconds = 0;
        let cdTimer = null;
        let pollTimer = null;

        function startCountdown() {
            if (cdTimer) clearInterval(cdTimer);
            cdTimer = setInterval(() => {
                if (countdownSeconds <= 0) {
                    clearInterval(cdTimer);
                    if (cdEl) cdEl.textContent = 'expired';
                    if (checkBtn) checkBtn.disabled = true;
                    return;
                }
                countdownSeconds--;
                const m = Math.floor(countdownSeconds / 60);
                const s = countdownSeconds % 60;
                if (cdEl) cdEl.textContent = m + ':' + String(s).padStart(2, '0');
            }, 1000);
        }

        async function checkPayment() {
            if (checkBtn) { checkBtn.disabled = true; checkBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Checking…'; }
            if (checkSt) checkSt.textContent = '';
            try {
                const res  = await fetch(queryUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                });
                const json = await res.json();
                if (json.paid || json.payment_status === 'paid') {
                    clearInterval(pollTimer);
                    clearInterval(cdTimer);
                    if (successEl) successEl.style.display = '';
                    const confirmBtn = panel.parentElement?.querySelector('.paid-confirm');
                    if (confirmBtn) confirmBtn.classList.remove('d-none');
                    if (checkBtn) checkBtn.style.display = 'none';
                    return;
                }
                if (checkSt) checkSt.textContent = json.throttled ? 'Wait a moment.' : 'Not received yet.';
            } catch {
                if (checkSt) checkSt.textContent = 'Network error.';
            }
            if (checkBtn) { checkBtn.disabled = false; checkBtn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i>Check payment'; }
        }

        btn.addEventListener('click', async () => {
            panel.style.display = '';
            if (loading) loading.style.display = '';
            if (acctBlock) acctBlock.style.display = 'none';
            btn.disabled = true;
            try {
                const res  = await fetch(initiateUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                });
                const json = await res.json();
                if (!json.success) {
                    if (loading) loading.innerHTML = '<span class="text-danger">' + (json.message || 'Failed to generate account.') + '</span>';
                    btn.disabled = false;
                    return;
                }
                if (loading) loading.style.display = 'none';
                if (bankName) bankName.textContent = json.virtual_bank_name ?? '—';
                if (acctNo)   acctNo.textContent   = json.virtual_account_number ?? '—';
                if (acctAmt)  acctAmt.textContent  = '₦' + Number(json.amount).toLocaleString(undefined, {minimumFractionDigits:2});
                countdownSeconds = json.seconds_remaining || 0;
                if (acctBlock) acctBlock.style.display = '';
                startCountdown();
                pollTimer = setInterval(checkPayment, 20000);
                if (checkBtn) checkBtn.addEventListener('click', checkPayment);
            } catch {
                if (loading) loading.innerHTML = '<span class="text-danger">Network error — try again.</span>';
                btn.disabled = false;
            }
        });
    });

    // ── Confirm Pickup buttons ────────────────────────────────────────────────
    document.querySelectorAll('.confirm-pickup-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const ref    = btn.dataset.order;
            const action = btn.dataset.action;
            Swal.fire({
                title: 'Confirm Pickup',
                html: `<p>The customer has collected order <strong>${ref}</strong>?</p>
                       <p class="text-muted small mb-0">This will mark the order as delivered.</p>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, confirm',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#198754',
            }).then(result => {
                if (!result.isConfirmed) return;
                const form  = document.createElement('form');
                form.method = 'post';
                form.action = action;
                const inp   = document.createElement('input');
                inp.type = 'hidden'; inp.name = '_token'; inp.value = csrf;
                form.appendChild(inp);
                document.body.appendChild(form);
                form.submit();
            });
        });
    });
})();
</script>
@endpush
@endsection

@push('scripts')
    <script>
document.addEventListener('DOMContentLoaded', function(){
    if (!document.querySelector('#orders-table')) return;

    const table = $('#orders-table').DataTable({
        serverSide: true,
        processing: true,
        ajax: {
            url: '{{ route('pickup-portal.dashboard.data') }}',
            type: 'GET',
            data: function(d){
                d.status = document.getElementById('orders-filter-status')?.value || 'all';
                d.payment_status = document.getElementById('orders-filter-payment')?.value || 'all';
                d.from = document.getElementById('orders-filter-from')?.value || null;
                d.to = document.getElementById('orders-filter-to')?.value || null;
            }
        ,
            error: function(xhr, status, err){
                let msg = 'Failed to load orders.';
                if (xhr.status === 401) msg = 'Session expired — please re-login to the pickup portal.';
                else if (xhr.responseJSON && xhr.responseJSON.error) msg = xhr.responseJSON.error;
                $('#orders-error').text(msg).removeClass('d-none');
            }
        },
        columns: [
            { data: null, orderable: false, searchable: false, render: function(data,type,row,meta){ return meta.row + meta.settings._iDisplayStart + 1; } },
            { data: 'reference', render: function(d,t,r){ return `<a href="#order-${r.id}" class="order-link">${d}</a>`; } },
            { data: 'order_date' },
            { data: 'customer' },
            { data: 'grand_total', className: 'text-end' },
            { data: 'status', className: 'text-center' },
            { data: 'payment_status' },
            { data: null, orderable:false, searchable:false, render: function(d,t,r){
                return `<button class="btn btn-sm btn-link details-btn" data-id="${r.id}">Details</button>`;
            } }
        ],
        createdRow: function(row,data){
            $(row).attr('id', 'dt-order-'+data.id);
        }
    });

    // no-op

    // handle details button -> expand row with items
    $('#orders-table').on('click', '.details-btn', function(){
        const tr = $(this).closest('tr');
        const row = table.row(tr);
        if (row.child.isShown()) {
            row.child.hide(); tr.removeClass('shown');
        } else {
            const items = row.data().items || [];
            let html = '<div class="p-2">';
            html += '<table class="table table-sm mb-0"><thead><tr><th>Product</th><th>Variant</th><th>Qty</th><th class="text-end">Price</th></tr></thead><tbody>';
            items.forEach(it => {
                html += `<tr><td>${it.product||''}</td><td class="text-muted small">${it.variant||''}</td><td>${it.quantity}</td><td class="text-end">₦${it.line_total||''}</td></tr>`;
            });
            html += '</tbody></table></div>';
            row.child(html).show(); tr.addClass('shown');
        }
    });

    // filter handlers
    ['orders-filter-status','orders-filter-payment','orders-filter-from','orders-filter-to'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', function(){ table.ajax.reload(); });
    });
    document.getElementById('orders-filter-clear')?.addEventListener('click', function(e){
        e.preventDefault();
        document.getElementById('orders-filter-status').value = 'all';
        document.getElementById('orders-filter-payment').value = 'all';
        document.getElementById('orders-filter-from').value = '';
        document.getElementById('orders-filter-to').value = '';
        table.ajax.reload();
    });
    document.getElementById('orders-filter-apply')?.addEventListener('click', function(e){
        e.preventDefault();
        // hide previous errors and log filter values for debugging
        $('#orders-error').addClass('d-none').text('');
        try { console.debug('Applying filters', {
            status: document.getElementById('orders-filter-status')?.value,
            payment: document.getElementById('orders-filter-payment')?.value,
            from: document.getElementById('orders-filter-from')?.value,
            to: document.getElementById('orders-filter-to')?.value
        }); } catch(e){}
        table.ajax.reload();
    });

    // click order-link scrolls to card if present
    $('#orders-table').on('click', '.order-link', function(e){
        e.preventDefault();
        const href = $(this).attr('href');
        const id = href.replace('#','');
        const el = document.getElementById(id);
        if (el) {
            el.scrollIntoView({behavior:'smooth', block:'center'});
            el.classList.add('highlight');
            setTimeout(()=> el.classList.remove('highlight'), 2500);
        }
    });
});
</script>
@endpush
