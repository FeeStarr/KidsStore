@extends('layouts.admin', ['title' => 'Edit Purchase '.$purchase->display_number])
@section('content')
<h3 class="mb-3">Edit Purchase {{ $purchase->display_number }}</h3>

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

// Existing items grouped by product for pre-population.
// Structure: [ productId => [ variantId => item ] ]
$existingGroups = [];
foreach ($purchase->items as $item) {
    $v      = $item->variant;
    $pid    = $item->product_id;
    $vid    = $item->product_variant_id;
    $existingGroups[$pid][$vid] = [
        'qty'      => $item->quantity,
        'cost'     => (float) $item->cost_price,
        'shipping' => (float) $item->shipping_fee,
        'packaging'=> (float) $item->packaging_cost,
        'other'    => (float) $item->other_costs,
        'sell'     => (float) $item->selling_price,
        'discount' => (float) $item->discount,
    ];
}
$existingGroupsJson = json_encode($existingGroups);

// Batch totals for pre-populating the selling price calculator.
$allocShipping  = (float) $purchase->total_shipping_fee;
$allocPackaging = (float) $purchase->total_packaging_cost;
$allocOther     = (float) $purchase->total_other_costs;
@endphp

<form method="post" action="{{ route('admin.purchases.update', $purchase) }}" id="purchase-form">
    @csrf
    @method('PUT')

    {{-- ── Header fields ─────────────────────────────────────────────────── --}}
    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <label class="form-label">Date *</label>
            <input type="date" name="purchase_date"
                   value="{{ old('purchase_date', $purchase->purchase_date->toDateString()) }}"
                   class="form-control" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">Supplier</label>
            <select name="supplier_id" class="form-select">
                <option value="">-</option>
                @foreach($suppliers as $s)
                    <option value="{{ $s->id }}" @selected($purchase->supplier_id == $s->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Status</label>
            <input class="form-control" value="{{ ucfirst($purchase->status) }}" disabled>
            <small class="text-muted">Status cannot be changed here.</small>
        </div>
        <div class="col-md-2">
            <label class="form-label">Purchase Number</label>
            <input name="purchase_number" class="form-control"
                   value="{{ old('purchase_number', $purchase->purchase_number) }}">
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
                Loading existing items…
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
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Total Shipping (₦)</label>
                    <input type="number" step="0.01" min="0" id="alloc-shipping" class="form-control"
                           value="{{ number_format($allocShipping, 2, '.', '') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Total Packaging (₦)</label>
                    <input type="number" step="0.01" min="0" id="alloc-packaging" class="form-control"
                           value="{{ number_format($allocPackaging, 2, '.', '') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Other Batch Costs (₦)</label>
                    <input type="number" step="0.01" min="0" id="alloc-other" class="form-control"
                           value="{{ number_format($allocOther, 2, '.', '') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Pickup Station Fee (%)</label>
                    <input type="number" step="0.01" min="0" max="100" id="alloc-pickup" class="form-control"
                           value="{{ number_format((float) $purchase->pickup_fee_pct, 2, '.', '') }}">
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

    {{-- Hidden items populated by JS on submit --}}
    <div id="items-hidden"></div>

    <div class="mb-3">
        <label class="form-label">Note</label>
        <textarea name="note" class="form-control" rows="2">{{ old('note', $purchase->note) }}</textarea>
    </div>
    
    <div class="d-flex align-items-center justify-content-between">
        <div class="d-flex gap-2">
            <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Save Changes</button>
            <a href="{{ route('admin.purchases.show', $purchase) }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
        @if($purchase->status === 'pending')
            <button id="delete-purchase" class="btn btn-danger">
                <i class="bi bi-trash me-1"></i>Delete Purchase
            </button>
        @endif
    </div>
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
    const PRODUCTS       = @json($productsJson);
    const EXISTING       = @json($existingGroups);

    const fmt = n => '₦' + Number(n).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
    const flt = v => parseFloat(v) || 0;

    function allVariantRows() {
        return Array.from(document.querySelectorAll('.variant-row'));
    }

    function recalcRow(tr) {
        const qty  = flt(tr.querySelector('.vqty')?.value);
        const cost = flt(tr.querySelector('.vcost')?.value);
        const sell = flt(tr.querySelector('.vsell')?.value);
        const ship = flt(tr.dataset.ship || 0);
        const pack = flt(tr.dataset.pack || 0);
        const othr = flt(tr.dataset.othr || 0);
        const landed    = cost + ship + pack + othr;
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

    function applyMarkupToRow(tr) {
        const cost   = flt(tr.querySelector('.vcost')?.value);
        const ship   = flt(tr.dataset.ship || 0);
        const pack   = flt(tr.dataset.pack || 0);
        const othr   = flt(tr.dataset.othr || 0);
        const markup = flt(tr.querySelector('.vmarkup')?.value);
        if (cost <= 0) return;
        const sellEl = tr.querySelector('.vsell');
        if (sellEl) sellEl.value = ((cost + ship + pack + othr) * (1 + markup / 100)).toFixed(2);
        recalcRow(tr);
    }

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

    function addGroup(preProduct, preColor, preItems) {
        document.getElementById('empty-msg')?.remove();

        // Derive representative cost & markup from the first saved item (if any).
        let seedCost   = 0;
        let seedMarkup = 30;
        if (preItems) {
            const firstItem = Object.values(preItems)[0];
            if (firstItem) {
                seedCost = firstItem.cost ?? 0;
                // Derive markup from cost→sell ratio: markup = (sell/cost - 1) × 100
                if (seedCost > 0 && firstItem.sell > 0) {
                    seedMarkup = parseFloat((((firstItem.sell / seedCost) - 1) * 100).toFixed(1));
                }
            }
        }

        const card = document.createElement('div');
        card.className = 'border rounded p-3 mb-2 group-card';

        card.innerHTML = `
            <div class="row g-2 align-items-end mb-2">
                <div class="col-md-5 d-flex align-items-end gap-2">
                    <div class="gp-thumb flex-shrink-0 d-none" style="width:56px;height:56px;border-radius:.5rem;overflow:hidden;border:1px solid #dee2e6;background:#f8f9fa;">
                        <img src="" style="width:100%;height:100%;object-fit:cover" alt="">
                    </div>
                    <div class="flex-grow-1 position-relative">
                        <label class="form-label form-label-sm fw-semibold">Product</label>
                        <input type="hidden" class="gp-product" value="${preProduct || ''}">
                        <div class="form-select form-select-sm gp-product-trigger">
                            <img src="" style="display:none" alt="">
                            <span class="text-muted gp-product-label">${preProduct ? '' : '- Select Product -'}</span>
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
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm gp-cost"
                           value="${seedCost.toFixed(2)}">
                </div>
                <div class="col-md-1">
                    <label class="form-label form-label-sm fw-semibold">Markup %</label>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm gp-markup"
                           value="${seedMarkup}">
                </div>
                <div class="col-md-1 d-flex gap-1 align-items-end">
                    <button type="button" class="btn btn-sm btn-outline-danger gp-remove w-100" title="Remove group">
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
                <div class="col text-muted small">Copies cost to every row and recalculates selling prices.</div>
            </div>
            <div class="gp-variants-wrap" style="display:none">
                <table class="table table-sm table-bordered mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width:44px"></th>
                            <th>Age Range</th><th>Size</th>
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

        document.getElementById('groups-container').appendChild(card);

        const productSel = card.querySelector('.gp-product');
        const colorSel   = card.querySelector('.gp-color');
        const costInp    = card.querySelector('.gp-cost');
        const markupInp  = card.querySelector('.gp-markup');
        const applyRow   = card.querySelector('.gp-apply-row');
        const varWrap    = card.querySelector('.gp-variants-wrap');
        const tbody      = card.querySelector('.gp-tbody');

        // ── Custom product dropdown ───────────────────────────────────────
        const trigger     = card.querySelector('.gp-product-trigger');
        const label       = card.querySelector('.gp-product-label');
        const triggerImg  = card.querySelector('.gp-product-trigger img');
        const dropdown    = card.querySelector('.gp-product-dropdown');
        const searchInp   = card.querySelector('.gp-product-search');
        const optionsWrap = card.querySelector('.gp-product-options');

        function getSelectedIds() {
            return Array.from(document.querySelectorAll('.gp-product')).map(i => i.value).filter(Boolean);
        }

        function renderProductOptions(filter) {
            const q = (filter || '').toLowerCase();
            const selectedIds = getSelectedIds();
            const currentVal = productSel.value;

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

        // If pre-selected, set the label
        if (preProduct) {
            const pp = PRODUCTS.find(p => p.id == preProduct);
            if (pp) {
                label.textContent = pp.name;
                label.classList.remove('text-muted');
                if (pp.image) { triggerImg.src = pp.image; triggerImg.style.display = ''; }
            }
        }
        renderProductOptions();

        function populateColors(product, selectColor) {
            colorSel.innerHTML = '<option value="">- Select Color -</option>';
            tbody.innerHTML = '';
            varWrap.style.display = 'none';
            applyRow.style.setProperty('display', 'none', 'important');
            if (!product) { colorSel.disabled = true; return; }

            const colors = [...new Set(product.variants.map(v => v.color || ''))];
            colors.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c;
                opt.textContent = c || '- Default -';
                if (c === selectColor) opt.selected = true;
                colorSel.appendChild(opt);
            });
            colorSel.disabled = false;

            if (selectColor) {
                loadVariantRows(product, selectColor, preItems);
            } else {
                loadVariantRows(product, null, preItems);
            }
        }

        productSel.addEventListener('change', () => {
            const product = PRODUCTS.find(p => p.id == productSel.value);
            populateColors(product, undefined);
        });

        colorSel.addEventListener('change', () => {
            const product = PRODUCTS.find(p => p.id == productSel.value);
            tbody.innerHTML = '';
            varWrap.style.display = 'none';
            applyRow.style.setProperty('display', 'none', 'important');
            if (!product) return;
            loadVariantRows(product, colorSel.value, null);
        });

        card.querySelector('.gp-apply-cost').addEventListener('click', () => applySharedToGroup(card));
        costInp.addEventListener('change', () => { if (tbody.querySelector('.variant-row')) applySharedToGroup(card); });
        markupInp.addEventListener('change', () => { if (tbody.querySelector('.variant-row')) applySharedToGroup(card); });

        card.querySelector('.gp-remove').addEventListener('click', () => {
            card.remove();
            if (!document.querySelector('.group-card')) {
                const msg = document.createElement('p');
                msg.id = 'empty-msg';
                msg.className = 'text-center text-muted small py-3 mb-0';
                msg.textContent = 'Click "Add Product Group" to start.';
                document.getElementById('groups-container').appendChild(msg);
            }
            updateGrandTotal();
        });

        function loadVariantRows(product, color, savedItems) {
            tbody.innerHTML = '';
            const variants = (color === null || color === undefined)
                ? product.variants
                : product.variants.filter(v => (v.color || '') === (color || ''));
            if (!variants.length) return;

            variants.forEach(v => {
                const saved = savedItems ? (savedItems[v.id] ?? null) : null;
                const qty   = saved ? saved.qty  : 0;
                const cost  = saved ? saved.cost : flt(costInp.value);
                const sell  = saved ? saved.sell : v.selling_price;
                const ship  = saved ? saved.shipping  : 0;
                const pack  = saved ? saved.packaging : 0;
                const othr  = saved ? saved.other     : 0;

                const tr = document.createElement('tr');
                tr.className = 'variant-row';
                tr.dataset.variantId = v.id;
                tr.dataset.productId = product.id;
                tr.dataset.ship = ship;
                tr.dataset.pack = pack;
                tr.dataset.othr = othr;

                // Derive markup from saved cost/sell if possible
                const derivedMarkup = (cost > 0 && sell > 0)
                    ? (((sell / cost) - 1) * 100).toFixed(1)
                    : flt(markupInp.value);

                const stock = v.stock || 0;
                const stockBadge = stock > 0
                    ? `<span class="badge bg-success-subtle text-success" style="font-size:.65rem;">${stock}</span>`
                    : `<span class="badge bg-danger-subtle text-danger" style="font-size:.65rem;">0</span>`;

                tr.innerHTML = `
                    <td><img src="${v.image || ''}" style="width:36px;height:36px;object-fit:cover;border-radius:.375rem;border:1px solid #dee2e6;${v.image ? '' : 'display:none'}" class="vthumb" alt=""></td>
                    <td class="small">${v.age || '<span class="text-muted">-</span>'}</td>
                    <td class="small">${v.size || '<span class="text-muted">-</span>'}</td>
                    <td class="text-center">${stockBadge}</td>
                    <td><input type="number" min="0" value="${qty}" class="form-control form-control-sm vqty"></td>
                    <td><input type="number" step="0.01" min="0" value="${cost.toFixed(2)}" class="form-control form-control-sm vcost"></td>
                    <td><input type="number" step="0.01" min="0" value="${derivedMarkup}" class="form-control form-control-sm vmarkup"></td>
                    <td><input type="number" step="0.01" min="0" value="${sell.toFixed(2)}" class="form-control form-control-sm vsell"></td>
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
                recalcRow(tr);
            });

            varWrap.style.display = '';
            applyRow.style.removeProperty('display');
        }

        // Pre-populate if data passed in
        if (preProduct) {
            const product = PRODUCTS.find(p => p.id == preProduct);
            if (product) populateColors(product, preColor);
        }
    }

    function applySharedToGroup(card) {
        const cost   = flt(card.querySelector('.gp-cost')?.value);
        const markup = flt(card.querySelector('.gp-markup')?.value);
        card.querySelectorAll('.variant-row').forEach(tr => {
            tr.querySelector('.vcost').value   = cost.toFixed(2);
            tr.querySelector('.vmarkup').value = markup;
            applyMarkupToRow(tr);
        });
    }

    // ── Selling price calculator ──────────────────────────────────────────────
    document.getElementById('apply-pricing').addEventListener('click', function () {
        const num = id => flt(document.getElementById(id)?.value);
        const totalShipping  = num('alloc-shipping');
        const totalPackaging = num('alloc-packaging');
        const totalOther     = num('alloc-other');
        const pickupPct      = num('alloc-pickup');
        const rows = allVariantRows();
        if (!rows.length) return;

        const baseTotal = rows.reduce((s, tr) =>
            s + flt(tr.querySelector('.vcost')?.value) * flt(tr.querySelector('.vqty')?.value), 0);

        if (baseTotal <= 0) {
            Swal.fire({ icon: 'warning', title: 'Enter cost & qty first',
                text: 'Add at least one row with a cost and quantity.' });
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
            const landed     = cost + linShip + linPack + linOthr;
            const withPickup = landed * (1 + pickupPct / 100);
            const rowMarkup  = flt(tr.querySelector('.vmarkup')?.value);
            tr.querySelector('.vsell').value = (withPickup * (1 + rowMarkup / 100)).toFixed(2);
            recalcRow(tr);
        });

        Swal.fire({ toast: true, position: 'top-end', icon: 'success', timer: 2500,
            showConfirmButton: false, title: 'Selling prices calculated' });
    });

    // ── Form submit → flatten hidden inputs ───────────────────────────────────
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
            inp.type = 'hidden'; inp.name = name; inp.value = value;
            container.appendChild(inp);
        };
        // Store pickup fee % for future pre-population.
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

    // ── Wire buttons ──────────────────────────────────────────────────────────
    document.getElementById('add-group').addEventListener('click', () => addGroup());
    document.getElementById('add-group-2').addEventListener('click', () => addGroup());

    // ── Pre-populate from existing purchase items ─────────────────────────────
    Object.entries(EXISTING).forEach(([pid, items]) => {
        addGroup(parseInt(pid), undefined, items);
    });

    // If no existing items, start with an empty group
    if (!document.querySelector('.group-card')) {
        addGroup();
    }

    // Delete button handler (AJAX) to avoid accidental nested form submits
    const delBtn = document.getElementById('delete-purchase');
    if (delBtn) {
        delBtn.addEventListener('click', function () {
            if (!confirm('Are you sure you want to delete this purchase? This action cannot be undone.')) return;
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            fetch("{{ route('admin.purchases.destroy', $purchase) }}", {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json'
                }
            }).then(r => {
                if (r.ok) {
                    window.location.href = "{{ route('admin.purchases.index') }}";
                } else {
                    r.text().then(t => Swal.fire({ icon: 'error', title: 'Delete failed', text: t }));
                }
            }).catch(e => Swal.fire({ icon: 'error', title: 'Delete failed', text: e }));
        });
    }
})();
</script>
@endpush
@endsection
