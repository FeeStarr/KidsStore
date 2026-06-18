@extends('layouts.admin', ['title' => 'Edit Purchase '.$purchase->display_number])
@section('content')
<h3 class="mb-3">Edit Purchase {{ $purchase->display_number }}</h3>

@php
// Build JS-friendly product catalogue (same as create view).
$productsJson = $products->map(fn($p) => [
    'id'       => $p->id,
    'name'     => $p->name,
    'variants' => $p->variants->map(fn($v) => [
        'id'            => $v->id,
        'sku'           => $v->sku,
        'color'         => $v->colorRef?->name ?? '',
        'age'           => $v->ageRange?->name ?? '',
        'size'          => $v->sizeRef?->name ?? '',
        'selling_price' => (float) $v->selling_price,
        'label'         => $v->options_label,
    ])->values(),
])->values();

// Existing items grouped by product+color for pre-population.
// Structure: [ productId => [ colorName => [ variantId => item ] ] ]
$existingGroups = [];
foreach ($purchase->items as $item) {
    $v      = $item->variant;
    $pid    = $item->product_id;
    $color  = $v?->colorRef?->name ?? '';
    $vid    = $item->product_variant_id;
    $existingGroups[$pid][$color][$vid] = [
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
                <option value="">—</option>
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
            <small class="text-muted">— allocate batch-wide costs to all rows proportionally</small>
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
    
    <div class="d-flex gap-2">
        <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Save Changes</button>
        <a href="{{ route('admin.purchases.show', $purchase) }}" class="btn btn-link">Cancel</a>
        
        @if($purchase->status === 'pending')
            <form method="post" action="{{ route('admin.purchases.destroy', $purchase) }}" 
                  style="display: inline;" 
                  onsubmit="return confirm('Are you sure you want to delete this purchase? This action cannot be undone.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger ms-auto">
                    <i class="bi bi-trash me-1"></i>Delete Purchase
                </button>
            </form>
        @endif
    </div>
</form>

@push('scripts')
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
        if (ltEl) ltEl.textContent = qty > 0 ? fmt(lineTotal) : '—';
        const mpEl = tr.querySelector('.vmargin');
        if (mpEl && sell > 0 && cost > 0) {
            const margin = ((sell - cost) / cost) * 100;
            mpEl.textContent = margin.toFixed(1) + '%';
            mpEl.className = 'vmargin small ' + (margin >= 0 ? 'text-success' : 'text-danger');
        } else if (mpEl) {
            mpEl.textContent = '—';
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

        const pOpts = PRODUCTS.map(p =>
            `<option value="${p.id}" ${preProduct == p.id ? 'selected' : ''}>${p.name}</option>`
        ).join('');

        card.innerHTML = `
            <div class="row g-2 align-items-end mb-2">
                <div class="col-md-5">
                    <label class="form-label form-label-sm fw-semibold">Product</label>
                    <select class="form-select form-select-sm gp-product">
                        <option value="">— Select Product —</option>
                        ${pOpts}
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label form-label-sm fw-semibold">Color / Style</label>
                    <select class="form-select form-select-sm gp-color" disabled>
                        <option value="">— Select Color —</option>
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
                            <th>Age Range</th><th>Size</th><th>Age Range</th>
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


        function populateColors(product, selectColor) {
            colorSel.innerHTML = '<option value="">— Select Color —</option>';
            tbody.innerHTML = '';
            varWrap.style.display = 'none';
            applyRow.style.setProperty('display', 'none', 'important');
            if (!product) { colorSel.disabled = true; return; }

            const colors = [...new Set(product.variants.map(v => v.color || ''))];
            colors.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c;
                opt.textContent = c || '— Default —';
                if (c === selectColor) opt.selected = true;
                colorSel.appendChild(opt);
            });
            colorSel.disabled = false;

            // If a color was specifically provided, load only that color; otherwise load all variants for the product
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

                tr.innerHTML = `
                    <td class="small">${v.age || '<span class="text-muted">—</span>'}</td>
                    <td class="small">${v.size || '<span class="text-muted">—</span>'}</td>
                    <td class="text-muted small">${v.age || '<span class="text-muted">—</span>'}</td>
                    <td><input type="number" min="0" value="${qty}" class="form-control form-control-sm vqty"></td>
                    <td><input type="number" step="0.01" min="0" value="${cost.toFixed(2)}" class="form-control form-control-sm vcost"></td>
                    <td><input type="number" step="0.01" min="0" value="${derivedMarkup}" class="form-control form-control-sm vmarkup"></td>
                    <td><input type="number" step="0.01" min="0" value="${sell.toFixed(2)}" class="form-control form-control-sm vsell"></td>
                    <td class="text-end vline text-muted">—</td>
                    <td class="text-end vmargin small text-muted">—</td>
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
            add(`items[${n}][product_variant_id]`, tr.dataset.variantId);
            add(`items[${n}][product_id]`,         tr.dataset.productId);
            add(`items[${n}][quantity]`,            flt(tr.querySelector('.vqty')?.value));
            add(`items[${n}][cost_price]`,          flt(tr.querySelector('.vcost')?.value).toFixed(2));
            add(`items[${n}][selling_price]`,       flt(tr.querySelector('.vsell')?.value).toFixed(2));
            add(`items[${n}][shipping_fee]`,        flt(tr.dataset.ship || 0).toFixed(6));
            add(`items[${n}][packaging_cost]`,      flt(tr.dataset.pack || 0).toFixed(6));
            add(`items[${n}][other_costs]`,         flt(tr.dataset.othr || 0).toFixed(6));
            add(`items[${n}][discount]`,            '0');
        });
        this.submit();
    });

    // ── Wire buttons ──────────────────────────────────────────────────────────
    document.getElementById('add-group').addEventListener('click', () => addGroup());
    document.getElementById('add-group-2').addEventListener('click', () => addGroup());

    // ── Pre-populate from existing purchase items ─────────────────────────────
    const seen = new Set();
    Object.entries(EXISTING).forEach(([pid, colorMap]) => {
        Object.entries(colorMap).forEach(([color, items]) => {
            const key = `${pid}|${color}`;
            if (seen.has(key)) return;
            seen.add(key);
            addGroup(parseInt(pid), color, items);
        });
    });

    // If no existing items, start with an empty group
    if (!document.querySelector('.group-card')) {
        addGroup();
    }
})();
</script>
@endpush
@endsection
