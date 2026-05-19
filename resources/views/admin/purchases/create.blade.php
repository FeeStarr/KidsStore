@extends('layouts.admin', ['title' => 'New Purchase'])
@section('content')
<h3 class="mb-3">New Purchase</h3>
<form method="post" action="{{ route('admin.purchases.store') }}" id="purchase-form">
    @csrf
    <div class="row g-3 mb-3">
        <div class="col-md-3"><label class="form-label">Date *</label>
            <input type="date" name="purchase_date" value="{{ old('purchase_date', now()->toDateString()) }}" class="form-control" required></div>
        <div class="col-md-4"><label class="form-label">Supplier</label>
            <select name="supplier_id" class="form-select">
                <option value="">—</option>
                @foreach($suppliers as $s)
                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3"><label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="pending">Pending</option>
                <option value="received">Received (update inventory)</option>
            </select>
        </div>
        <div class="col-md-2"><label class="form-label">Reference</label>
            <input name="reference" class="form-control" placeholder="auto"></div>
    </div>

    <div class="card mb-3"><div class="card-header">Items</div>
        <div class="card-body p-0">
            <table class="table mb-0 align-middle" id="items-table">
                <thead><tr>
                    <th>Product</th><th style="width:80px">Qty</th>
                    <th style="width:120px">Unit Cost</th>
                    <th class="text-end" style="width:130px">Gross Cost</th>
                    <th style="width:100px">Markup %</th>
                    <th style="width:130px">Selling Price</th>
                    <th class="text-end" style="width:130px">Line Total</th>
                    <th class="text-end" style="width:90px">Margin %</th>
                    <th style="width:40px"></th>
                </tr></thead>
                <tbody></tbody>
            </table>
        </div>
        <div class="card-footer"><button type="button" class="btn btn-sm btn-outline-primary" id="add-row"><i class="bi bi-plus"></i> Add Item</button></div>
    </div>

    <div class="card mb-3 border-warning-subtle">
        <div class="card-header bg-warning-subtle">
            <i class="bi bi-calculator"></i> Selling Price Calculator
            <small class="text-muted">— factor batch-wide costs into each item's selling price</small>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">
                Enter total batch costs (will be allocated to each line proportionally based on the line's <em>cost &times; quantity</em>).
                Pickup-station fee % and Markup % are then applied to the landed unit cost to compute the selling price.
            </p>
            <div class="row g-3">
                <div class="col-md-3"><label class="form-label">Total Shipping (₦)</label>
                    <input type="number" step="0.01" min="0" id="alloc-shipping" class="form-control" value="0"></div>
                <div class="col-md-3"><label class="form-label">Total Packaging (₦)</label>
                    <input type="number" step="0.01" min="0" id="alloc-packaging" class="form-control" value="0"></div>
                <div class="col-md-3"><label class="form-label">Other Batch Costs (₦)</label>
                    <input type="number" step="0.01" min="0" id="alloc-other" class="form-control" value="0"></div>
                <div class="col-md-3"><label class="form-label">Pickup Station Fee (%)</label>
                    <input type="number" step="0.01" min="0" max="100" id="alloc-pickup" class="form-control" value="0"></div>
                <div class="col-12 d-flex align-items-end gap-2">
                    <button type="button" class="btn btn-warning" id="apply-pricing"><i class="bi bi-magic"></i> Distribute &amp; Compute Selling Prices</button>
                    <span class="text-muted small">Each row uses its own <strong>Markup %</strong>. You can still tweak any selling price afterwards.</span>
                </div>
            </div>
        </div>
    </div>

    <div class="mb-3"><label class="form-label">Note</label><textarea name="note" class="form-control" rows="2"></textarea></div>
    <button class="btn btn-primary">Create Purchase</button>
    <a href="{{ route('admin.purchases.index') }}" class="btn btn-link">Cancel</a>
</form>

<template id="row-template">
    <tr>
        <td><select name="items[__i__][product_variant_id]" class="form-select form-select-sm variant-select" required>
            <option value="">—</option>
            @foreach($products as $p)
                @if($p->variants->count() > 1)
                    <optgroup label="{{ $p->name }}">
                        @foreach($p->variants as $v)
                            <option value="{{ $v->id }}" data-product-id="{{ $p->id }}" data-price="{{ $v->selling_price }}">
                                {{ $p->name }} — {{ $v->options_label ?: $v->name ?: $v->sku }} ({{ $v->sku }})
                            </option>
                        @endforeach
                    </optgroup>
                @else
                    @php($v = $p->variants->first())
                    @if($v)
                        <option value="{{ $v->id }}" data-product-id="{{ $p->id }}" data-price="{{ $v->selling_price }}">
                            {{ $p->name }} ({{ $v->sku }})
                        </option>
                    @endif
                @endif
            @endforeach
        </select>
        <input type="hidden" name="items[__i__][product_id]" class="hidden-product-id">
        </td>
        <td><input type="number" min="1" value="1" name="items[__i__][quantity]" class="form-control form-control-sm qty" required></td>
        <td><input type="number" step="0.01" value="0" name="items[__i__][cost_price]" class="form-control form-control-sm cost" required></td>
        <td class="text-end gross-cost text-muted">—</td>
        <td><input type="number" step="0.01" min="0" value="30" class="form-control form-control-sm markup" placeholder="30"></td>
        <td><input type="number" step="0.01" value="0" name="items[__i__][selling_price]" class="form-control form-control-sm sell"></td>
        <td class="text-end line-total">0.00</td>
        <td class="text-end margin-pct text-muted">—</td>
        <td>
            <input type="hidden" value="0" name="items[__i__][shipping_fee]">
            <input type="hidden" value="0" name="items[__i__][packaging_cost]">
            <input type="hidden" value="0" name="items[__i__][other_costs]">
            <input type="hidden" value="0" name="items[__i__][discount]">
            <button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="bi bi-x"></i></button>
        </td>
    </tr>
</template>

@push('scripts')
<script>
let idx = 0;
const tbody = document.querySelector('#items-table tbody');
const tpl = document.querySelector('#row-template').innerHTML;
function addRow() {
    const html = tpl.replaceAll('__i__', idx++);
    const tr = document.createElement('tr');
    tr.innerHTML = html.replace(/^<tr>|<\/tr>$/g, '');
    tbody.appendChild(tr);
    bindRow(tr);
}
function bindRow(tr) {
    tr.querySelectorAll('.qty, .cost, .sell').forEach(inp => inp.addEventListener('input', () => recalc(tr)));
    // Typing a markup % auto-computes a suggested selling price (if cost is set).
    const markupInput = tr.querySelector('.markup');
    if (markupInput) {
        markupInput.addEventListener('input', () => applyMarkup(tr));
    }
    tr.querySelector('.remove-row').addEventListener('click', () => { tr.remove(); });
    tr.querySelector('select[name$="[product_variant_id]"]').addEventListener('change', e => {
        const opt = e.target.selectedOptions[0];
        const sell = tr.querySelector('input[name$="[selling_price]"]');
        const hiddenPid = tr.querySelector('.hidden-product-id');
        if (opt) {
            if (opt.dataset.productId) hiddenPid.value = opt.dataset.productId;
            if (opt.dataset.price) { sell.value = opt.dataset.price; recalc(tr); }
        } else {
            hiddenPid.value = '';
        }
    });
}
function applyMarkup(tr) {
    const num = sel => parseFloat(tr.querySelector(sel)?.value || 0);
    const unitCost = num('input[name$="[cost_price]"]')
                   + num('input[name$="[shipping_fee]"]')
                   + num('input[name$="[packaging_cost]"]')
                   + num('input[name$="[other_costs]"]');
    if (unitCost <= 0) return;
    const markupPct = num('.markup');
    tr.querySelector('input[name$="[selling_price]"]').value = (unitCost * (1 + markupPct / 100)).toFixed(2);
    recalc(tr);
}
function recalc(tr) {
    const num = sel => parseFloat(tr.querySelector(sel)?.value || 0);
    const qty = num('.qty');
    const unitCost = num('input[name$="[cost_price]"]')
                   + num('input[name$="[shipping_fee]"]')
                   + num('input[name$="[packaging_cost]"]')
                   + num('input[name$="[other_costs]"]');
    const discountPct = num('input[name$="[discount]"]');
    const sellPrice   = num('input[name$="[selling_price]"]');
    const netUnitCost = unitCost * (1 - discountPct / 100);
    const lineTotal = netUnitCost * qty;
    tr.querySelector('.line-total').textContent = '\u20a6' + lineTotal.toFixed(2);

    const lu = tr.querySelector('.gross-cost');
    if (netUnitCost > 0 && qty > 0) {
        lu.textContent = '\u20a6' + (netUnitCost * qty).toFixed(2);
        lu.classList.remove('text-muted');
    } else {
        lu.textContent = '\u2014';
        lu.classList.add('text-muted');
    }

    const mp = tr.querySelector('.margin-pct');
    if (sellPrice > 0 && netUnitCost > 0) {
        const margin = ((sellPrice - netUnitCost) / netUnitCost) * 100;
        mp.textContent = margin.toFixed(1) + '%';
        mp.classList.toggle('text-success', margin >= 0);
        mp.classList.toggle('text-danger',  margin <  0);
        mp.classList.toggle('text-muted',   false);
    } else {
        mp.textContent = '\u2014';
        mp.classList.remove('text-success', 'text-danger');
        mp.classList.add('text-muted');
    }
}
document.getElementById('add-row').addEventListener('click', addRow);
addRow();

// Selling-price calculator: distribute batch costs across lines, apply pickup% + markup%.
document.getElementById('apply-pricing').addEventListener('click', function () {
    const num = (id) => parseFloat(document.getElementById(id).value || 0);
    const totalShipping  = num('alloc-shipping');
    const totalPackaging = num('alloc-packaging');
    const totalOther     = num('alloc-other');
    const pickupPct      = num('alloc-pickup');

    const rows = Array.from(tbody.querySelectorAll('tr'));
    if (!rows.length) { return; }

    // Allocation base = sum(cost_price * qty) across rows.
    const baseTotal = rows.reduce((sum, tr) => {
        const cost = parseFloat(tr.querySelector('input[name$="[cost_price]"]').value || 0);
        const qty  = parseFloat(tr.querySelector('input[name$="[quantity]"]').value || 0);
        return sum + (cost * qty);
    }, 0);

    if (baseTotal <= 0) {
        Swal.fire({ icon: 'warning', title: 'Enter cost & qty first',
            text: 'Add at least one item with a cost and quantity before computing selling prices.' });
        return;
    }

    rows.forEach(tr => {
        const cost = parseFloat(tr.querySelector('input[name$="[cost_price]"]').value || 0);
        const qty  = parseFloat(tr.querySelector('input[name$="[quantity]"]').value || 1);
        const lineBase = cost * qty;
        const share = lineBase / baseTotal;

        const linShip  = (totalShipping  * share) / qty;
        const linPack  = (totalPackaging * share) / qty;
        const linOther = (totalOther     * share) / qty;

        tr.querySelector('input[name$="[shipping_fee]"]').value   = linShip.toFixed(2);
        tr.querySelector('input[name$="[packaging_cost]"]').value = linPack.toFixed(2);
        tr.querySelector('input[name$="[other_costs]"]').value    = linOther.toFixed(2);

        const landedUnit = cost + linShip + linPack + linOther;
        const withPickup = landedUnit * (1 + pickupPct / 100);
        const rowMarkup  = parseFloat(tr.querySelector('.markup')?.value || 0);
        const sellingUnit = withPickup * (1 + rowMarkup / 100);

        tr.querySelector('input[name$="[selling_price]"]').value = sellingUnit.toFixed(2);
        recalc(tr);
    });

    Swal.fire({
        toast: true, position: 'top-end', icon: 'success', timer: 2500, showConfirmButton: false,
        title: 'Selling prices calculated'
    });
});
</script>
@endpush
@endsection
