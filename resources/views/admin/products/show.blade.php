@extends('layouts.admin', ['title' => $product->name])
@section('content')
<div class="d-flex justify-content-between mb-3">
    <h3>{{ $product->name }} <small class="text-muted">{{ $product->sku }}</small></h3>
    <div class="d-flex gap-2">
        @if($product->isInactive())
            <form action="{{ route('admin.products.toggle-status', $product) }}" method="post">
                @csrf
                <button class="btn btn-success" onclick="return confirm('Activate this product?')"><i class="bi bi-check-circle"></i> Activate</button>
            </form>
        @else
            <form action="{{ route('admin.products.toggle-status', $product) }}" method="post">
                @csrf
                <button class="btn btn-warning" onclick="return confirm('Deactivate this product? All variants will also be deactivated.')"><i class="bi bi-x-circle"></i> Deactivate</button>
            </form>
        @endif
        <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-outline-primary">Edit</a>
    </div>
</div>
<div class="row g-3">
    <div class="col-md-6">
        <div class="card"><div class="card-body">
            <div class="row g-2">
                @forelse($product->images as $img)
                    <div class="col-4"><img src="{{ $img->url }}" class="img-fluid rounded {{ $img->is_primary ? 'border border-success border-3' : '' }}" loading="lazy" decoding="async"></div>
                @empty
                    <div class="text-muted">No images uploaded.</div>
                @endforelse
            </div>
        </div></div>
    </div>
    <div class="col-md-6">
        <div class="card mb-3"><div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-4">Category</dt><dd class="col-sm-8">{{ $product->category?->name ?? '-' }}</dd>
                <dt class="col-sm-4">Age Group</dt><dd class="col-sm-8">{{ !empty($product->age_group) ? implode(', ', (array) $product->age_group) : '-' }}</dd>
                <dt class="col-sm-4">Gender</dt><dd class="col-sm-8">{{ $product->gender ?? '-' }}</dd>
                <dt class="col-sm-4">Brand</dt><dd class="col-sm-8">{{ $product->brand ?? '-' }}</dd>
                <dt class="col-sm-4">Status</dt><dd class="col-sm-8"><span class="badge {{ $product->isInactive() ? 'text-bg-danger' : 'text-bg-success' }}">{{ $product->status ?? 'inactive' }}</span></dd>
                <dt class="col-sm-4">Selling Price</dt><dd class="col-sm-8">₦{{ number_format($product->selling_price, 2) }}</dd>
                <dt class="col-sm-4">Cost Price</dt><dd class="col-sm-8">₦{{ number_format($product->cost_price, 2) }}</dd>
                <dt class="col-sm-4">Product Discount</dt><dd class="col-sm-8">{{ number_format($product->discount, 2) }}% <small class="text-muted">(global)</small></dd>
                <dt class="col-sm-4">Stock</dt><dd class="col-sm-8">{{ $product->inventory?->quantity ?? 0 }} (reorder at {{ $product->inventory?->reorder_level ?? '-' }})</dd>
            </dl>
        </div></div>
        <div class="card"><div class="card-header">Recent Inventory Movements</div>
            <table class="table mb-0">
                <thead><tr><th>Date</th><th>Type</th><th class="text-end">Qty</th><th>Note</th></tr></thead>
                <tbody>
                @forelse($product->inventoryMovements()->latest()->take(15)->get() as $m)
                    <tr>
                        <td>{{ $m->created_at->format('Y-m-d H:i') }}</td>
                        <td><span class="badge text-bg-light">{{ $m->type }}</span></td>
                        <td class="text-end {{ $m->quantity < 0 ? 'text-danger':'text-success' }}">{{ $m->quantity }}</td>
                        <td class="small text-muted">{{ $m->note }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted p-3">No movements.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
