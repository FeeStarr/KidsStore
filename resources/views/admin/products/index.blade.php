@extends('layouts.admin', ['title' => 'Products'])

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Products</h3>
    <a href="{{ route('admin.products.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add Product</a>
</div>

<div class="card"><div class="card-body">
<table id="products-table" class="table align-middle w-100">
    <thead>
    <tr>
        <th data-dt-no-export></th>
        <th>SKU</th>
        <th>Name</th>
        <th>Category</th>
        <th>Status</th>
        <th class="text-end">Price (NGN)</th>
        <th class="text-end">Discount %</th>
        <th class="text-end">Stock</th>
        <th data-dt-no-export class="text-end">Actions</th>
    </tr>
    </thead>
    <tbody>
    @foreach($products as $p)
        <tr>
            <td style="width:64px">
                @if($p->primaryImage)
                    <img src="{{ $p->primaryImage->url }}" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:.35rem;" loading="lazy" decoding="async">
                @else
                    <div class="bg-light text-muted text-center" style="width:48px;height:48px;line-height:48px;border-radius:.35rem;"><i class="bi bi-image"></i></div>
                @endif
            </td>
            <td>{{ $p->sku }}</td>
            <td>{{ $p->name }}</td>
            <td>{{ $p->category?->name ?? '-' }}</td>
            <td><span class="badge {{ ($p->status ?? 'inactive') === 'active' ? 'text-bg-success' : 'text-bg-danger' }}">{{ $p->status ?? (($p->is_active ?? false) ? 'active' : 'inactive') }}</span></td>
            <td class="text-end">{{ number_format($p->selling_price, 2) }}</td>
            <td class="text-end">{{ number_format($p->discount, 2) }}</td>
            <td class="text-end">{{ $p->stock_quantity }}</td>
            <td class="text-end text-nowrap">
                <a href="{{ route('admin.products.show', $p) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                <a href="{{ route('admin.products.edit', $p) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                @if(($p->status ?? 'inactive') === 'active')
                    <form action="{{ route('admin.products.toggle-status', $p) }}" method="post" class="d-inline">
                        @csrf
                        <button class="btn btn-sm btn-warning" title="Deactivate" onclick="return confirm('Deactivate this product? All variants will also be deactivated.')"><i class="bi bi-x-circle"></i></button>
                    </form>
                @else
                    <form action="{{ route('admin.products.toggle-status', $p) }}" method="post" class="d-inline">
                        @csrf
                        <button class="btn btn-sm btn-success" title="Activate" onclick="return confirm('Activate this product?')"><i class="bi bi-check-circle"></i></button>
                    </form>
                @endif
                <form action="{{ route('admin.products.destroy', $p) }}" method="post" class="d-inline"
                      data-confirm="This product will be permanently deleted." data-confirm-title="Delete Product?"
                      data-confirm-yes="Yes, delete">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
</div></div>

@push('scripts')
<script>
$(function () {
    $('#products-table').DataTable({
        order: [[2, 'asc']],
        pageLength: 15,
        lengthMenu: [[10, 15, 25, 50, 100, -1], [10, 15, 25, 50, 100, 'All']],
        columnDefs: [
            { targets: 0, orderable: false, searchable: false },
            { targets: -1, orderable: false, searchable: false }
        ],
        layout: {
            topStart: {
                buttons: [
                    { extend: 'copy',  className: 'btn btn-sm btn-primary',   exportOptions: { columns: ':not([data-dt-no-export])' } },
                    { extend: 'csv',   className: 'btn btn-sm btn-success',   filename: 'products', exportOptions: { columns: ':not([data-dt-no-export])' } },
                    { extend: 'excel', className: 'btn btn-sm btn-success',   filename: 'products', exportOptions: { columns: ':not([data-dt-no-export])' } },
                    { extend: 'pdf',   className: 'btn btn-sm btn-danger',    filename: 'products', orientation: 'landscape', pageSize: 'A4', exportOptions: { columns: ':not([data-dt-no-export])' } },
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
