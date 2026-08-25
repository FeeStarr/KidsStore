@extends('layouts.admin', ['title' => 'Inventory'])
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Inventory</h3>
    <form>
        <label class="me-2"><input type="checkbox" name="low_stock" value="1" @checked(request('low_stock'))> Show only low stock</label>
        <button class="btn btn-sm btn-outline-secondary">Apply</button>
    </form>
</div>

<div class="card"><div class="card-body">
<table id="inventory-table" class="table align-middle w-100">
    <thead>
    <tr>
        <th>Product</th>
        <th>Variant</th>
        <th>SKU</th>
        <th class="text-end">Quantity on Hand</th>
        <th class="text-end">Reorder Level</th>
        <th>Status</th>
        <th data-dt-no-export class="text-end">Actions</th>
    </tr>
    </thead>
    <tbody>
    @foreach($inventories as $inv)
        @php $v = $inv->variant; @endphp
        <tr>
            <td>{{ $v?->product?->name ?? $inv->product?->name }}</td>
            <td>
                @if($v)
                    {{ $v->options_label ?: ($v->name ?: '-') }}
                    <div class="small text-muted">
                        {{ $v->colorRef?->name ?: ($v->color ?: 'No Color') }} /
                        {{ $v->sizeRef?->name ?: ($v->size ?: 'No Size') }} /
                        {{ $v->ageRange?->name ?: ((is_array($v->age_group ?? null) && !empty($v->age_group)) ? ($v->age_group[0] ?? 'No Age') : 'No Age') }}
                    </div>
                @else
                    -
                @endif
            </td>
            <td>{{ $v?->sku ?? $inv->product?->sku }}</td>
            <td class="text-end">{{ $inv->current_quantity }}</td>
            <td class="text-end">{{ $inv->reorder_level }}</td>
            <td>
                @if($inv->isLowStock())
                    <span class="badge text-bg-warning">Low</span>
                @else
                    <span class="badge text-bg-success">OK</span>
                @endif
            </td>
            <td class="text-end">
                <div class="d-flex gap-2 justify-content-end align-items-center">
                    <form action="{{ route('admin.inventory.reorder', $inv) }}" method="post" class="d-flex gap-1">
                        @csrf @method('PATCH')
                        <input type="number" name="reorder_level" value="{{ $inv->reorder_level }}" class="form-control form-control-sm" style="width:90px">
                        <button class="btn btn-sm btn-outline-primary" title="Save reorder level"><i class="bi bi-save"></i></button>
                    </form>
                    <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#adjust-{{ $inv->id }}">
                        <i class="bi bi-sliders"></i> Decrease
                    </button>
                </div>

                <div class="modal fade text-start" id="adjust-{{ $inv->id }}" tabindex="-1">
                    <div class="modal-dialog">
                        <form class="modal-content" method="post" action="{{ route('admin.inventory.adjust', $inv) }}">
                            @csrf
                            <div class="modal-header">
                                <h5 class="modal-title"><i class="bi bi-dash-circle text-warning"></i> Decrease Stock - {{ $v?->display_label ?? $inv->product?->name }}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p class="text-muted small mb-3">Current quantity on hand: <strong>{{ $inv->current_quantity }}</strong></p>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label">Quantity to remove</label>
                                        <input type="number" name="quantity" min="1" max="{{ $inv->current_quantity }}" class="form-control" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Reason</label>
                                        <select name="reason" class="form-select" required>
                                            <option value="" disabled selected>Choose a reason…</option>
                                            <option>Stock-take correction</option>
                                            <option>Damaged / breakage</option>
                                            <option>Lost / theft</option>
                                            <option>Returned to supplier</option>
                                            <option>Other</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Note <small class="text-muted">(optional)</small></label>
                                        <textarea name="note" class="form-control" rows="2" maxlength="500" placeholder="Additional context…"></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button class="btn btn-warning"><i class="bi bi-check2-circle"></i> Apply Decrease</button>
                            </div>
                        </form>
                    </div>
                </div>
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
</div></div>
<p class="text-muted small mt-2"><i class="bi bi-info-circle"></i> Stock increases only through <strong>Purchases</strong>. Stock decreases through <strong>Orders</strong>. Use <strong>Decrease</strong> for corrections, damages, or write-offs - every adjustment is logged with a reason.</p>

@push('scripts')
<script>
$(function () {
    $('#inventory-table').DataTable({
        order: [[0, 'asc']],
        pageLength: 15,
        lengthMenu: [[10, 15, 25, 50, 100, -1], [10, 15, 25, 50, 100, 'All']],
        columnDefs: [
            { targets: -1, orderable: false, searchable: false }
        ],
        layout: {
            topStart: {
                buttons: [
                    { extend: 'copy',  className: 'btn btn-sm btn-primary',   exportOptions: { columns: ':not([data-dt-no-export])' } },
                    { extend: 'csv',   className: 'btn btn-sm btn-success',   filename: 'inventory', exportOptions: { columns: ':not([data-dt-no-export])' } },
                    { extend: 'excel', className: 'btn btn-sm btn-success',   filename: 'inventory', exportOptions: { columns: ':not([data-dt-no-export])' } },
                    { extend: 'pdf',   className: 'btn btn-sm btn-danger',    filename: 'inventory', orientation: 'landscape', pageSize: 'A4', exportOptions: { columns: ':not([data-dt-no-export])' } },
                    { extend: 'print', className: 'btn btn-sm btn-secondary', exportOptions: { columns: ':not([data-dt-no-export])' } }
                ]
            },
            topEnd: ['pageLength', 'search']
        }
    });
});
</script>
@endpush
@endsection
