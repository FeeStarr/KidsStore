@extends('layouts.admin', ['title' => 'Edit Prices - Purchase ' . $purchase->display_number])
@section('content')
<div class="d-flex justify-content-between mb-3">
    <h3>Edit Prices & Quantities - {{ $purchase->display_number }}</h3>
    <a href="{{ route('admin.purchases.show', $purchase) }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back to purchase
    </a>
</div>

<div class="alert alert-info">
    <i class="bi bi-info-circle me-1"></i>
    <strong>Quantity</strong> changes are reconciled against stock (stock is increased or decreased to match this purchase).
    <strong>Selling price</strong> changes are pushed to the product and variant immediately.
    Cost fields are read-only here &mdash; use <em>Edit</em> on a pending purchase to change them.
</div>

<form action="{{ route('admin.purchases.update-prices', $purchase) }}" method="post"
      data-confirm="Stock will be adjusted to match the new quantities and selling prices will be pushed to products. Continue?"
      data-confirm-title="Save corrections?" data-confirm-yes="Yes, save">
    @csrf
    @method('PUT')

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Lines ({{ $purchase->items->count() }})</span>
            <span class="text-muted small">Grand total: <strong id="grand-total">&#8358;{{ number_format($purchase->total_cost, 2) }}</strong></span>
        </div>
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th style="width:40px">#</th>
                        <th style="width:44px"></th>
                        <th>Product</th>
                        <th>Cost (read-only)</th>
                        <th style="width:110px">Qty</th>
                        <th style="width:150px">Selling Price</th>
                        <th class="text-end" style="width:130px">Line Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($purchase->items as $it)
                        @php
                            $thumb = $it->variant?->image?->url ?? $it->product->images->first()?->url ?? '';
                            $unitCost = (float) $it->cost_price + (float) $it->shipping_fee + (float) $it->packaging_cost + (float) $it->other_costs;
                        @endphp
                        <tr data-line="{{ $it->id }}"
                            data-unit-cost="{{ $unitCost }}"
                            data-discount="{{ (float) $it->discount }}">
                            <td class="text-muted">{{ $loop->iteration }}</td>
                            <td>
                                @if ($thumb)
                                    <img src="{{ $thumb }}" style="width:36px;height:36px;object-fit:cover;border-radius:.375rem;border:1px solid #dee2e6;" alt="" loading="lazy" decoding="async">
                                @else
                                    <span class="d-inline-flex align-items-center justify-content-center bg-light text-muted" style="width:36px;height:36px;object-fit:cover;border-radius:.375rem;font-size:.7rem;"><i class="bi bi-image"></i></span>
                                @endif
                            </td>
                            <td>
                                {{ $it->product->name }}
                                @if ($it->variant && $it->variant->options_label)
                                    <small class="text-muted d-block">{{ $it->variant->options_label }}</small>
                                @endif
                                @if ($it->variant)
                                    <small class="text-muted d-block">Stock: {{ $it->variant->inventory?->quantity ?? 0 }}</small>
                                @endif
                            </td>
                            <td class="text-muted small">
                                &#8358;{{ number_format($it->cost_price, 2) }} cost
                                @if ((float) $it->shipping_fee > 0)<br>+ &#8358;{{ number_format($it->shipping_fee, 2) }} shipping @endif
                                @if ((float) $it->packaging_cost > 0)<br>+ &#8358;{{ number_format($it->packaging_cost, 2) }} packaging @endif
                                @if ((float) $it->other_costs > 0)<br>+ &#8358;{{ number_format($it->other_costs, 2) }} other @endif
                                @if ((float) $it->discount > 0)<br>&minus; {{ number_format($it->discount, 2) }}% discount @endif
                            </td>
                            <td>
                                <input type="number" name="items[{{ $it->id }}][quantity]" id="qty-{{ $it->id }}"
                                       class="form-control form-control-sm qty-input" data-line="{{ $it->id }}"
                                       min="1" step="1" required
                                       value="{{ old('items.' . $it->id . '.quantity', $it->quantity) }}">
                            </td>
                            <td>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">&#8358;</span>
                                    <input type="number" name="items[{{ $it->id }}][selling_price]" id="price-{{ $it->id }}"
                                           class="form-control form-control-sm" min="0" step="0.01"
                                           value="{{ old('items.' . $it->id . '.selling_price', $it->selling_price) }}">
                                </div>
                            </td>
                            <td class="text-end" id="line-total-{{ $it->id }}">&#8358;{{ number_format($it->line_total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-3">
        <a href="{{ route('admin.purchases.show', $purchase) }}" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check2"></i> Save changes</button>
    </div>
</form>

<script>
(function () {
    const fmt = n => '\u20A6' + n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    function recalcLine(row) {
        const id = row.dataset.line;
        const unit = parseFloat(row.dataset.unitCost) || 0;
        const discount = parseFloat(row.dataset.discount) || 0;
        const qty = parseInt(document.getElementById('qty-' + id).value, 10) || 0;
        const net = unit - unit * (discount / 100);
        const total = net * qty;
        document.getElementById('line-total-' + id).textContent = fmt(total);
        return total;
    }

    function recalcGrand() {
        let grand = 0;
        document.querySelectorAll('tr[data-line]').forEach(row => { grand += recalcLine(row); });
        document.getElementById('grand-total').textContent = fmt(grand);
    }

    document.querySelectorAll('.qty-input').forEach(input => input.addEventListener('input', recalcGrand));
    recalcGrand();
})();
</script>
@endsection
