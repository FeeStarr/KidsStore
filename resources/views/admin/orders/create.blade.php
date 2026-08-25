@extends('layouts.admin', ['title' => 'New Order'])
@section('content')
<h3 class="mb-3">New Order</h3>
<form method="post" action="{{ route('admin.orders.store') }}" id="order-form">
    @csrf
    <div class="row g-3 mb-3">
        <div class="col-md-3"><label class="form-label">Date *</label>
            <input type="date" name="order_date" value="{{ now()->toDateString() }}" class="form-control" required></div>
        <div class="col-md-4"><label class="form-label">Customer</label>
            <select name="customer_id" class="form-select">
                <option value="">- Walk-in -</option>
                @foreach($customers as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3"><label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="pending">Pending</option>
                <option value="confirmed">Confirmed (decrement stock)</option>
            </select>
        </div>
        <div class="col-md-2"><label class="form-label">Reference</label>
            <input name="reference" class="form-control" placeholder="auto"></div>
    </div>

    <div class="card mb-3"><div class="card-header">Items</div>
        <div class="card-body p-0">
            <table class="table mb-0 align-middle" id="order-items">
                <thead><tr>
                    <th>Product</th><th>Stock</th><th>Qty</th><th>Unit Price</th><th>Discount %</th>
                    <th class="text-end">Line Total</th><th></th>
                </tr></thead>
                <tbody></tbody>
            </table>
        </div>
        <div class="card-footer"><button type="button" class="btn btn-sm btn-outline-primary" id="add-row"><i class="bi bi-plus"></i> Add Item</button></div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3"><label class="form-label">Order Discount (%)</label>
            <input type="number" step="0.01" min="0" max="100" name="discount" value="0" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">Shipping Fee (per order)</label>
            <input type="number" step="0.01" name="shipping_fee" value="0" class="form-control"></div>
        <div class="col-md-6"><label class="form-label">Note</label>
            <input name="note" class="form-control"></div>
    </div>

    <button class="btn btn-primary">Create Order</button>
    <a href="{{ route('admin.orders.index') }}" class="btn btn-link">Cancel</a>
</form>

<template id="order-row-template">
    <tr>
        <td><select name="items[__i__][product_variant_id]" class="form-select form-select-sm prod" required>
            <option value="">-</option>
            @foreach($products as $p)
                @if($p->variants->count() > 1)
                    <optgroup label="{{ $p->name }}">
                        @foreach($p->variants as $v)
                            <option value="{{ $v->id }}"
                                data-product-id="{{ $p->id }}"
                                data-price="{{ $v->selling_price }}"
                                data-stock="{{ $v->inventory?->quantity ?? 0 }}">
                                {{ $p->name }} - {{ $v->options_label ?: $v->name ?: $v->sku }}
                            </option>
                        @endforeach
                    </optgroup>
                @else
                    @php($v = $p->variants->first())
                    @if($v)
                        <option value="{{ $v->id }}"
                            data-product-id="{{ $p->id }}"
                            data-price="{{ $v->selling_price }}"
                            data-stock="{{ $v->inventory?->quantity ?? 0 }}">
                            {{ $p->name }}
                        </option>
                    @endif
                @endif
            @endforeach
        </select>
        <input type="hidden" name="items[__i__][product_id]" class="hidden-product-id">
        </td>
        <td class="stock">-</td>
        <td><input type="number" min="1" value="1" name="items[__i__][quantity]" class="form-control form-control-sm qty" required></td>
        <td><input type="number" step="0.01" value="0" name="items[__i__][unit_price]" class="form-control form-control-sm price"></td>
        <td><input type="number" step="0.01" min="0" max="100" value="0" name="items[__i__][discount]" class="form-control form-control-sm disc"></td>
        <td class="text-end line-total">0.00</td>
        <td><button type="button" class="btn btn-sm btn-outline-danger remove-row"><i class="bi bi-x"></i></button></td>
    </tr>
</template>

@push('scripts')
<script>
let idx = 0;
const tbody = document.querySelector('#order-items tbody');
const tpl = document.querySelector('#order-row-template').innerHTML;
function addRow() {
    const html = tpl.replaceAll('__i__', idx++);
    const tr = document.createElement('tr');
    tr.innerHTML = html.replace(/^<tr>|<\/tr>$/g, '');
    tbody.appendChild(tr);
    bind(tr);
}
function bind(tr) {
    tr.querySelector('.prod').addEventListener('change', e => {
        const o = e.target.selectedOptions[0];
        const hidden = tr.querySelector('.hidden-product-id');
        if (o && o.value) {
            tr.querySelector('.price').value = o.dataset.price ?? 0;
            tr.querySelector('.stock').textContent = o.dataset.stock ?? '-';
            hidden.value = o.dataset.productId ?? '';
        } else {
            tr.querySelector('.price').value = 0;
            tr.querySelector('.stock').textContent = '-';
            hidden.value = '';
        }
        recalc(tr);
    });
    tr.querySelectorAll('.qty,.price,.disc').forEach(i => i.addEventListener('input', () => recalc(tr)));
    tr.querySelector('.remove-row').addEventListener('click', () => tr.remove());
}
function recalc(tr) {
    const q = parseFloat(tr.querySelector('.qty').value || 0);
    const p = parseFloat(tr.querySelector('.price').value || 0);
    const d = parseFloat(tr.querySelector('.disc').value || 0);
    const lineTotal = p * (1 - d / 100) * q;
    tr.querySelector('.line-total').textContent = '\u20a6' + lineTotal.toFixed(2);
}
document.getElementById('add-row').addEventListener('click', addRow);
addRow();
</script>
@endpush
@endsection
