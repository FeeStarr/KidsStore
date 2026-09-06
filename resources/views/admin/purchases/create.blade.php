@extends('layouts.admin', ['title' => 'New Purchase'])
@section('content')
<h3 class="mb-3">New Purchase</h3>

@php
$productsJson = $products->map(fn($p) => [
    'id'           => $p->id,
    'name'         => $p->name,
    'image'        => $p->images->first()?->url ?? '',
    'total_stock'  => $p->variants->sum(fn($v) => $v->stock_quantity),
    'variants'     => $p->variants->map(fn($v) => [
        'id'            => $v->id,
        'sku'           => $v->sku,
        'color'         => $v->colorRef?->name ?? '',
        'age'           => $v->ageRange?->name ?? ($v->options['age'] ?? ''),
        'size'          => $v->sizeRef?->name ?? ($v->options['size'] ?? ($v->options['shoe_size'] ?? '')),
        'selling_price' => (float) $v->selling_price,
        'label'         => $v->options_label,
        'image'         => $v->image?->url ?? $p->images->first()?->url ?? '',
        'stock'         => $v->stock_quantity,
    ])->values(),
])->values();
@endphp

<form method="post" action="{{ route('admin.purchases.store') }}" id="purchase-form">
    @csrf

    {{-- ── Header fields ─────────────────────────────────────────────────── --}}
    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <label class="form-label">Date *</label>
            <input type="date" name="purchase_date"
                   value="{{ old('purchase_date', now()->toDateString()) }}"
                   class="form-control" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">Supplier</label>
            <select name="supplier_id" class="form-select">
                <option value="">-</option>
                @foreach($suppliers as $s)
                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="pending">Pending</option>
                <option value="received">Received (update inventory)</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Purchase Number</label>
            <input name="purchase_number" class="form-control" placeholder="auto">
        </div>
    </div>

    {{-- ── Product Groups ──────────────────────────────────────────────────── --}}
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-boxes me-1"></i>Purchase Items</span>
            <button type="button" class="btn btn-sm btn-primary" id="add-group">
                <i class="bi bi-plus-lg"></i> Add Product Group
            </button>
        </div>
        <div class="card-body p-2" id="groups-container">
            <p class="text-center text-muted small py-3 mb-0" id="empty-msg">
                Click "Add Product Group" to start adding items.
            </p>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <button type="button" class="btn btn-sm btn-outline-primary" id="add-group-2">
                <i class="bi bi-plus-lg"></i> Add Product Group
            </button>
            <span class="fw-semibold">Grand Total: <span id="grand-total" class="text-primary">₦0.00</span></span>
        </div>
    </div>

    {{-- ── Selling Price Calculator ─────────────────────────────────────────── --}}
    <div class="card mb-3 border-warning-subtle">
        <div class="card-header bg-warning-subtle">
            <i class="bi bi-calculator"></i> Selling Price Calculator
            <small class="text-muted">- allocate batch-wide costs to all rows proportionally</small>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">
                Enter total batch costs (allocated proportionally by <em>cost × qty</em>).
                Pickup-station fee % and each row's Markup % are then applied to the landed unit cost.
            </p>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Total Shipping (₦)</label>
                    <input type="number" step="0.01" min="0" id="alloc-shipping" class="form-control" value="0">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Total Packaging (₦)</label>
                    <input type="number" step="0.01" min="0" id="alloc-packaging" class="form-control" value="0">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Other Batch Costs (₦)</label>
                    <input type="number" step="0.01" min="0" id="alloc-other" class="form-control" value="0">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Pickup Station Fee (%)</label>
                    <input type="number" step="0.01" min="0" max="100" id="alloc-pickup" class="form-control" value="{{ old('pickup_fee_pct', isset($avgPickupPct) ? number_format($avgPickupPct, 2, '.', '') : '0') }}">
                </div>
                <div class="col-12 d-flex align-items-end gap-2">
                    <button type="button" class="btn btn-warning" id="apply-pricing">
                        <i class="bi bi-magic"></i> Distribute &amp; Compute Selling Prices
                    </button>
                    <span class="text-muted small">Each row uses its own <strong>Markup %</strong>. You can tweak any price afterwards.</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Note + Submit ────────────────────────────────────────────────────── --}}
    {{-- Hidden items - populated by JS on submit --}}
    <div id="items-hidden"></div>

    <div class="mb-3">
        <label class="form-label">Note</label>
        <textarea name="note" class="form-control" rows="2"></textarea>
    </div>
    <button class="btn btn-primary" type="submit">Create Purchase</button>
    <a href="{{ route('admin.purchases.index') }}" class="btn btn-link">Cancel</a>
</form>

@push('scripts')
<style>
.gp-product-trigger{display:flex;align-items:center;gap:.5rem;cursor:pointer;}
.gp-product-trigger img{width:32px;height:32px;object-fit:cover;border-radius:.375rem;border:1px solid #dee2e6;flex-shrink:0;}
.gp-product-dropdown{display:none;position:absolute;top:100%;left:0;right:0;z-index:1050;max-height:340px;overflow-y:auto;background:#fff;border:1px solid #dee2e6;border-radius:.375rem;box-shadow:0 4px 16px rgba(0,0,0,.15);margin-top:2px;}
.gp-product-dropdown.show{display:block;}
.gp-product-option{cursor:pointer;border-bottom:1px solid #f5f5f5;transition:background .1s;}
.gp-product-option:hover{background:#f8f9fa;}
.gp-product-option.active{background:#e8f4ff;}
.gp-product-option.gp-added{opacity:.5;}
.gp-product-option img{width:40px;height:40px;object-fit:cover;border-radius:.375rem;border:1px solid #dee2e6;flex-shrink:0;}
</style>
<script>
(function () {
    const PRODUCTS = @json($productsJson);

    // ── Helpers ──────────────────────────────────────────────────────────────
    const fmt = n => '₦' + Number(n).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
    const flt = v => parseFloat(v) || 0;

    // ── Group counter ─────────────────────────────────────────────────────────
    let gid = 0;

    // ── All variant rows (live NodeList substitute) ───────────────────────────
    function allVariantRows() {
        return Array.from(document.querySelectorAll('.variant-row'));
    }

    // ── Recalc a single variant row ───────────────────────────────────────────
    function recalcRow(tr) {
        const qty  = flt(tr.querySelector('.vqty')?.value);
        const cost = flt(tr.querySelector('.vcost')?.value);
        const sell = flt(tr.querySelector('.vsell')?.value);
        const ship = flt(tr.dataset.ship || 0);
        const pack = flt(tr.dataset.pack || 0);
        const othr = flt(tr.dataset.othr || 0);

        const landed   = cost + ship + pack + othr;
        const lineTotal = landed * qty;

        const ltEl = tr.querySelector('.vline');
        if (ltEl) ltEl.textContent = qty > 0 ? fmt(lineTotal) : '-';

        const mpEl = tr.querySelector('.vmargin');
        if (mpEl && sell > 0 && cost > 0) {
            const margin = ((sell - cost) / cost) * 100;
            mpEl.textContent = margin.toFixed(1) + '%';
            mpEl.className = 'vmargin small ' + (margin >= 0 ? 'text-success' : 'text-danger');
        } else if (mpEl) {
            mpEl.textContent = '-';
            mpEl.className = 'vmargin small text-muted';
        }

        updateGrandTotal();
    }

    // ── Apply markup to a row (cost → sell) ──────────────────────────────────
    function applyMarkupToRow(tr) {
        const cost   = flt(tr.querySelector('.vcost')?.value);
        const ship   = flt(tr.dataset.ship || 0);
        const pack   = flt(tr.dataset.pack || 0);
        const othr   = flt(tr.dataset.othr || 0);
        const markup = flt(tr.querySelector('.vmarkup')?.value);
        if (cost <= 0) return;
        const landed = cost + ship + pack + othr;
        const sellEl = tr.querySelector('.vsell');
        if (sellEl) sellEl.value = (landed * (1 + markup / 100)).toFixed(2);
        recalcRow(tr);
    }

    // ── Grand total ───────────────────────────────────────────────────────────
    function updateGrandTotal() {
        const total = allVariantRows().reduce((sum, tr) => {
            const qty  = flt(tr.querySelector('.vqty')?.value);
            const cost = flt(tr.querySelector('.vcost')?.value);
            const ship = flt(tr.dataset.ship || 0);
            const pack = flt(tr.dataset.pack || 0);
            const othr = flt(tr.dataset.othr || 0);
            return sum + (cost + ship + pack + othr) * qty;
        }, 0);
        const el = document.getElementById('grand-total');
        if (el) el.textContent = fmt(total);
    }

    // ── Sync product dropdowns - disable already-selected products in other groups
    function syncProductDropdowns() {
        document.querySelectorAll('.group-card').forEach(g => {
            const hidden = g.querySelector('.gp-product');
            if (hidden) {
                const optionsWrap = g.querySelector('.gp-product-options');
                if (optionsWrap) renderProductOptionsForGroup(g);
            }
        });
    }

    function renderProductOptionsForGroup(card) {
        const hidden = card.querySelector('.gp-product');
        const optionsWrap = card.querySelector('.gp-product-options');
        const searchInp = card.querySelector('.gp-product-search');
        if (!hidden || !optionsWrap) return;

        const q = (searchInp?.value || '').toLowerCase();
        const selectedIds = Array.from(document.querySelectorAll('.gp-product')).map(i => i.value).filter(Boolean);
        const currentVal = hidden.value;

        optionsWrap.innerHTML = PRODUCTS.filter(p => !q || p.name.toLowerCase().includes(q)).map(p => {
            const isAdded = selectedIds.includes(String(p.id)) && String(p.id) !== currentVal;
            const stockBadge = p.total_stock > 0
                ? `<span class="badge bg-success-subtle text-success" style="font-size:.65rem;">${p.total_stock} in stock</span>`
                : `<span class="badge bg-danger-subtle text-danger" style="font-size:.65rem;">Out of stock</span>`;
            const addedBadge = isAdded ? '<span class="badge bg-secondary ms-1" style="font-size:.6rem;">Added</span>' : '';
            const imgHtml = p.image
                ? `<img src="${p.image}" alt="">`
                : `<div style="width:40px;height:40px;border-radius:.375rem;background:#e9ecef;display:flex;align-items:center;justify-content:center;color:#adb5bd;font-size:.75rem;">No img</div>`;
            return `<div class="gp-product-option ${isAdded ? 'gp-added' : ''} ${String(p.id) === currentVal ? 'active' : ''}" data-id="${p.id}">
                ${imgHtml}
                <div class="flex-grow-1">
                    <div class="small fw-semibold">${p.name}</div>
                    <div>${stockBadge}${addedBadge}</div>
                </div>
            </div>`;
        }).join('');

        optionsWrap.querySelectorAll('.gp-product-option').forEach(opt => {
            opt.addEventListener('click', () => {
                const pid = opt.dataset.id;
                const product = PRODUCTS.find(p => p.id == pid);
                hidden.value = pid;
                const label = card.querySelector('.gp-product-label');
                const triggerImg = card.querySelector('.gp-product-trigger img');
                label.textContent = product.name;
                label.classList.remove('text-muted');
                if (product.image) { triggerImg.src = product.image; triggerImg.style.display = ''; }
                else { triggerImg.style.display = 'none'; }
                card.querySelector('.gp-product-dropdown').classList.remove('show');
                hidden.dispatchEvent(new Event('change'));
            });
        });
    }

    // ── Build a group card ────────────────────────────────────────────────────
    function addGroup() {
        const id = gid++;
        document.getElementById('empty-msg')?.remove();

        const card = document.createElement('div');
        card.className = 'border rounded p-3 mb-2 group-card';
        card.dataset.gid = id;

        card.innerHTML = `
            <div class="row g-2 align-items-end mb-2">
                <div class="col-md-4 d-flex align-items-end gap-2">
                    <div class="gp-thumb flex-shrink-0 d-none" style="width:56px;height:56px;border-radius:.5rem;overflow:hidden;border:1px solid #dee2e6;background:#f8f9fa;">
                        <img src="" style="width:100%;height:100%;object-fit:cover" alt="">
                    </div>
                    <div class="flex-grow-1 position-relative">
                        <label class="form-label form-label-sm fw-semibold">Product</label>
                        <input type="hidden" class="gp-product" value="">
                        <div class="form-select form-select-sm gp-product-trigger">
                            <img src="" style="display:none" alt="">
                            <span class="text-muted gp-product-label">- Select Product -</span>
                        </div>
                        <div class="gp-product-dropdown">
                            <div class="p-2 border-bottom" style="position:sticky;top:0;background:#fff;">
                                <input type="text" class="form-control form-control-sm gp-product-search" placeholder="Search products...">
                            </div>
                            <div class="gp-product-options"></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label form-label-sm fw-semibold">Color / Style</label>
                    <select class="form-select form-select-sm gp-color" disabled>
                        <option value="">- Select Color -</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm fw-semibold">Shared Cost (₦)</label>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm gp-cost" value="0" placeholder="Unit cost">
                </div>
                <div class="col-md-1">
                    <label class="form-label form-label-sm fw-semibold">Markup %</label>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm gp-markup" value="30">
                </div>
                <div class="col-md-2 d-flex gap-1 align-items-end justify-content-end">
                    <button type="button" class="btn btn-sm btn-outline-danger gp-remove" title="Remove group">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
            <div class="row g-2 align-items-center mb-2 gp-apply-row" style="display:none!important">
                <div class="col-auto">
                    <button type="button" class="btn btn-sm btn-outline-secondary gp-apply-cost">
                        <i class="bi bi-arrow-down-circle"></i> Apply shared cost &amp; markup to all rows
                    </button>
                </div>
                <div class="col text-muted small">Cost will be copied to every row in this group; selling prices will be recalculated.</div>
            </div>
            <div class="gp-variants-wrap" style="display:none">
                <table class="table table-sm table-bordered mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width:44px"></th>
                            <th>Age Range</th>
                            <th>Size</th>
                            <th>Stock</th>
                            <th style="width:80px">Qty</th>
                            <th style="width:110px">Unit Cost (₦)</th>
                            <th style="width:80px">Markup %</th>
                            <th style="width:120px">Sell Price (₦)</th>
                            <th class="text-end" style="width:110px">Line Total</th>
                            <th class="text-end" style="width:70px">Margin</th>
                            <th style="width:40px"></th>
                        </tr>
                    </thead>
                    <tbody class="gp-tbody"></tbody>
                </table>
            </div>`;

        const container = document.getElementById('groups-container');
        container.appendChild(card);

        const productSel = card.querySelector('.gp-product');
        const colorSel   = card.querySelector('.gp-color');
        const costInp    = card.querySelector('.gp-cost');
        const markupInp  = card.querySelector('.gp-markup');
        const applyRow   = card.querySelector('.gp-apply-row');
        const applyBtn   = card.querySelector('.gp-apply-cost');
        const varWrap    = card.querySelector('.gp-variants-wrap');
        const tbody      = card.querySelector('.gp-tbody');

        // ── Custom product dropdown ───────────────────────────────────────
        const trigger    = card.querySelector('.gp-product-trigger');
        const label      = card.querySelector('.gp-product-label');
        const triggerImg = card.querySelector('.gp-product-trigger img');
        const dropdown   = card.querySelector('.gp-product-dropdown');
        const searchInp  = card.querySelector('.gp-product-search');
        const optionsWrap = card.querySelector('.gp-product-options');

        function getSelectedIds() {
            return Array.from(document.querySelectorAll('.gp-product')).map(i => i.value).filter(Boolean);
        }

        function renderProductOptions(filter) {
            const q = (filter || '').toLowerCase();
            const selectedIds = getSelectedIds();
            const currentVal = productSel.value;

            const filtered = PRODUCTS.filter(p => {
                if (q && !p.name.toLowerCase().includes(q)) return false;
                return true;
            });

            optionsWrap.innerHTML = filtered.map(p => {
                const isAdded = selectedIds.includes(String(p.id)) && String(p.id) !== currentVal;
                const stockBadge = p.total_stock > 0
                    ? `<span class="badge bg-success-subtle text-success" style="font-size:.65rem;">${p.total_stock} in stock</span>`
                    : `<span class="badge bg-danger-subtle text-danger" style="font-size:.65rem;">Out of stock</span>`;
                const addedBadge = isAdded ? '<span class="badge bg-secondary ms-1" style="font-size:.6rem;">Added</span>' : '';
                const imgHtml = p.image
                    ? `<img src="${p.image}" alt="">`
                    : `<div style="width:40px;height:40px;border-radius:.375rem;background:#e9ecef;display:flex;align-items:center;justify-content:center;color:#adb5bd;font-size:.75rem;">No img</div>`;
                return `<div class="gp-product-option ${isAdded ? 'gp-added' : ''} ${String(p.id) === currentVal ? 'active' : ''}" data-id="${p.id}">
                    ${imgHtml}
                    <div class="flex-grow-1">
                        <div class="small fw-semibold">${p.name}</div>
                        <div>${stockBadge}${addedBadge}</div>
                    </div>
                </div>`;
            }).join('');

            optionsWrap.querySelectorAll('.gp-product-option').forEach(opt => {
                opt.addEventListener('click', () => {
                    const pid = opt.dataset.id;
                    const product = PRODUCTS.find(p => p.id == pid);
                    productSel.value = pid;
                    label.textContent = product.name;
                    label.classList.remove('text-muted');
                    if (product.image) { triggerImg.src = product.image; triggerImg.style.display = ''; }
                    else { triggerImg.style.display = 'none'; }
                    dropdown.classList.remove('show');
                    productSel.dispatchEvent(new Event('change'));
                });
            });
        }

        trigger.addEventListener('click', e => {
            e.stopPropagation();
            document.querySelectorAll('.gp-product-dropdown.show').forEach(d => d.classList.remove('show'));
            dropdown.classList.toggle('show');
            if (dropdown.classList.contains('show')) {
                searchInp.value = '';
                renderProductOptions();
                searchInp.focus();
            }
        });

        searchInp.addEventListener('input', () => renderProductOptions(searchInp.value));
        searchInp.addEventListener('click', e => e.stopPropagation());
        document.addEventListener('click', e => {
            if (!dropdown.contains(e.target) && e.target !== trigger && !trigger.contains(e.target)) {
                dropdown.classList.remove('show');
            }
        });

        renderProductOptions();

        // Product → populate colors + show thumbnail
        productSel.addEventListener('change', () => {
            const product = PRODUCTS.find(p => p.id == productSel.value);
            colorSel.innerHTML = '<option value="">- Select Color -</option>';
            tbody.innerHTML = '';
            varWrap.style.display = 'none';
            applyRow.style.setProperty('display', 'none', 'important');

            const thumb = card.querySelector('.gp-thumb');
            if (thumb) {
                if (product && product.image) {
                    thumb.querySelector('img').src = product.image;
                    thumb.classList.remove('d-none');
                } else {
                    thumb.classList.add('d-none');
                }
            }

            renderProductOptions(searchInp?.value || '');

            if (!product) { colorSel.disabled = true; return; }

            const colors = [...new Set(product.variants.map(v => v.color || ''))];
            colors.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c;
                opt.textContent = c || '- Default -';
                colorSel.appendChild(opt);
            });
            colorSel.disabled = false;

            loadVariantRows(product, null, tbody, varWrap, applyRow, costInp, markupInp);
        });

        // Color → load variant rows
        colorSel.addEventListener('change', () => {
            const product = PRODUCTS.find(p => p.id == productSel.value);
            tbody.innerHTML = '';
            varWrap.style.display = 'none';
            applyRow.style.setProperty('display', 'none', 'important');
            if (!product || !colorSel.value && colorSel.value !== '') return;
            loadVariantRows(product, colorSel.value, tbody, varWrap, applyRow, costInp, markupInp);
        });

        // Apply shared cost & markup to all rows in group
        applyBtn.addEventListener('click', () => {
            applySharedToGroup(card);
        });

        // Auto-apply when cost/markup changes
        costInp.addEventListener('change', () => { if (tbody.querySelector('.variant-row')) applySharedToGroup(card); });
        markupInp.addEventListener('change', () => { if (tbody.querySelector('.variant-row')) applySharedToGroup(card); });

        // Remove group
        card.querySelector('.gp-remove').addEventListener('click', () => {
            card.remove();
            if (!document.querySelector('.group-card')) {
                const msg = document.createElement('p');
                msg.id = 'empty-msg';
                msg.className = 'text-center text-muted small py-3 mb-0';
                msg.textContent = 'Click "Add Product Group" to start adding items.';
                document.getElementById('groups-container').appendChild(msg);
            }
            syncProductDropdowns();
            updateGrandTotal();
        });
    }

    // ── Load variant rows for a product+color combo ───────────────────────────
        function loadVariantRows(product, color, tbody, varWrap, applyRow, costInp, markupInp) {
        const variants = (color === null || color === undefined)
            ? product.variants
            : product.variants.filter(v => (v.color || '') === (color || ''));
        if (!variants.length) return;

        variants.forEach(v => {
            const tr = document.createElement('tr');
            tr.className = 'variant-row';
            tr.dataset.variantId = v.id;
            tr.dataset.productId = product.id;
            tr.dataset.ship = 0;
            tr.dataset.pack = 0;
            tr.dataset.othr = 0;

            const stock = v.stock || 0;
            const stockBadge = stock > 0
                ? `<span class="badge bg-success-subtle text-success" style="font-size:.65rem;">${stock}</span>`
                : `<span class="badge bg-danger-subtle text-danger" style="font-size:.65rem;">0</span>`;

            tr.innerHTML = `
                <td><img src="${v.image || ''}" style="width:36px;height:36px;object-fit:cover;border-radius:.375rem;border:1px solid #dee2e6;${v.image ? '' : 'display:none'}" class="vthumb" alt=""></td>
                <td class="small">${v.age || '<span class="text-muted">-</span>'}</td>
                <td class="small">${v.size || '<span class="text-muted">-</span>'}</td>
                <td class="text-center">${stockBadge}</td>
                <td><input type="number" min="0" value="" placeholder="-" class="form-control form-control-sm vqty"></td>
                <td><input type="number" step="0.01" min="0" value="" placeholder="-" class="form-control form-control-sm vcost"></td>
                <td><input type="number" step="0.01" min="0" value="${flt(markupInp.value)}" class="form-control form-control-sm vmarkup"></td>
                <td><input type="number" step="0.01" min="0" value="" placeholder="-" class="form-control form-control-sm vsell"></td>
                <td class="text-end vline text-muted">-</td>
                <td class="text-end vmargin small text-muted">-</td>
                <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger vrow-delete" title="Delete this item"><i class="bi bi-trash"></i></button></td>`;

            tr.querySelectorAll('.vqty, .vcost, .vsell').forEach(inp => inp.addEventListener('input', () => recalcRow(tr)));
            tr.querySelector('.vmarkup').addEventListener('input', () => applyMarkupToRow(tr));
            tr.querySelector('.vcost').addEventListener('change', () => applyMarkupToRow(tr));
            tr.querySelector('.vrow-delete').addEventListener('click', () => {
                tr.remove();
                updateGrandTotal();
            });

            tbody.appendChild(tr);
        });

        varWrap.style.display = '';
        applyRow.style.removeProperty('display');

        // Seed sell prices from current cost/markup
        applySharedToGroup(tbody.closest('.group-card'));
    }

    // ── Apply shared cost + markup from group header to all rows ──────────────
    function applySharedToGroup(card) {
        const costInp   = card.querySelector('.gp-cost');
        const markupInp = card.querySelector('.gp-markup');
        const cost      = flt(costInp?.value);
        const markup    = flt(markupInp?.value);

        card.querySelectorAll('.variant-row').forEach(tr => {
            tr.querySelector('.vcost').value   = cost.toFixed(2);
            tr.querySelector('.vmarkup').value = markup;
            applyMarkupToRow(tr);
        });
    }

    // ── Selling price calculator ──────────────────────────────────────────────
    document.getElementById('apply-pricing').addEventListener('click', function () {
        const num = id => parseFloat(document.getElementById(id)?.value || 0);
        const totalShipping  = num('alloc-shipping');
        const totalPackaging = num('alloc-packaging');
        const totalOther     = num('alloc-other');
        const pickupPct      = num('alloc-pickup');

        const rows = allVariantRows();
        if (!rows.length) return;

        const baseTotal = rows.reduce((sum, tr) => {
            return sum + flt(tr.querySelector('.vcost')?.value) * flt(tr.querySelector('.vqty')?.value);
        }, 0);

        if (baseTotal <= 0) {
            Swal.fire({ icon: 'warning', title: 'Enter cost & qty first',
                text: 'Add at least one row with a cost and quantity before computing selling prices.' });
            return;
        }

        rows.forEach(tr => {
            const cost = flt(tr.querySelector('.vcost')?.value);
            const qty  = flt(tr.querySelector('.vqty')?.value) || 1;
            const share = (cost * qty) / baseTotal;

            const linShip = (totalShipping  * share) / qty;
            const linPack = (totalPackaging * share) / qty;
            const linOthr = (totalOther     * share) / qty;

            tr.dataset.ship = linShip.toFixed(4);
            tr.dataset.pack = linPack.toFixed(4);
            tr.dataset.othr = linOthr.toFixed(4);

            const landed      = cost + linShip + linPack + linOthr;
            const withPickup  = landed * (1 + pickupPct / 100);
            const rowMarkup   = flt(tr.querySelector('.vmarkup')?.value);
            const sellingUnit = withPickup * (1 + rowMarkup / 100);

            tr.querySelector('.vsell').value = sellingUnit.toFixed(2);
            recalcRow(tr);
        });

        Swal.fire({ toast: true, position: 'top-end', icon: 'success', timer: 2500,
            showConfirmButton: false, title: 'Selling prices calculated' });
    });

    // ── Form submit → flatten to items[n] hidden inputs ──────────────────────
    document.getElementById('purchase-form').addEventListener('submit', function (e) {
        e.preventDefault();

        const container = document.getElementById('items-hidden');
        container.innerHTML = '';

        const rows = allVariantRows().filter(tr => flt(tr.querySelector('.vqty')?.value) > 0);

        if (!rows.length) {
            Swal.fire({ icon: 'warning', title: 'No items', text: 'Enter a quantity > 0 for at least one row.' });
            return;
        }

        const add = (name, value) => {
            const inp = document.createElement('input');
            inp.type  = 'hidden';
            inp.name  = name;
            inp.value = value;
            container.appendChild(inp);
        };

        // Store pickup fee % so it can be pre-populated on edit.
        add('pickup_fee_pct', flt(document.getElementById('alloc-pickup')?.value).toFixed(2));

        rows.forEach((tr, n) => {
            add(`items[${n}][product_variant_id]`, parseInt(tr.dataset.variantId, 10));
            add(`items[${n}][product_id]`,         parseInt(tr.dataset.productId, 10));
            add(`items[${n}][quantity]`,            Math.floor(flt(tr.querySelector('.vqty')?.value)));
            add(`items[${n}][cost_price]`,          flt(tr.querySelector('.vcost')?.value).toFixed(2));
            add(`items[${n}][selling_price]`,       flt(tr.querySelector('.vsell')?.value).toFixed(2));
            add(`items[${n}][shipping_fee]`,        flt(tr.dataset.ship || 0).toFixed(6));
            add(`items[${n}][packaging_cost]`,      flt(tr.dataset.pack || 0).toFixed(6));
            add(`items[${n}][other_costs]`,         flt(tr.dataset.othr || 0).toFixed(6));
            add(`items[${n}][discount]`,            '0');
            // Include pickup fee pct per item so it's stored with each row
            add(`items[${n}][pickup_fee_pct]`,      flt(document.getElementById('alloc-pickup')?.value).toFixed(2));
        });

        this.submit();
    });

    // ── Prevent Enter key from submitting the form ───────────────────────────
    document.getElementById('purchase-form').addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.tagName !== 'BUTTON' && e.target.type !== 'submit') {
            e.preventDefault();
        }
    });

    // ── Wire up both "Add Product Group" buttons ──────────────────────────────
    document.getElementById('add-group').addEventListener('click', addGroup);
    document.getElementById('add-group-2').addEventListener('click', addGroup);

    // Start with one group open
    addGroup();
})();
</script>
@endpush
@endsection

