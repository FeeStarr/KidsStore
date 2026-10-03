@extends('layouts.admin', ['title' => 'Product Reviews'])

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Product Reviews</h3>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card"><div class="card-body py-3">
            <div class="text-muted small text-uppercase">Total reviews</div>
            <div class="fs-4 fw-bold mb-0">{{ $totalReviews }}</div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card"><div class="card-body py-3">
            <div class="text-muted small text-uppercase">Average rating</div>
            <div class="fs-4 fw-bold mb-0">
                {{ $totalReviews > 0 ? number_format($averageRating, 1) : '-' }}
                <span class="fs-6" style="color:#ff8c42;">
                    @for($i = 1; $i <= 5; $i++)
                        <i class="bi bi-{{ $totalReviews > 0 && $i <= round($averageRating) ? 'star-fill' : 'star' }}"></i>
                    @endfor
                </span>
            </div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card"><div class="card-body py-3">
            <div class="text-muted small text-uppercase">Products reviewed</div>
            <div class="fs-4 fw-bold mb-0">{{ $reviewedProducts }}</div>
        </div></div>
    </div>
</div>

<div class="card mb-3"><div class="card-body py-2">
    <form method="get" action="{{ route('admin.product-reviews.index') }}" class="d-flex flex-wrap gap-2 align-items-end">
        <div>
            <label class="form-label small mb-1" for="filter_product">Product</label>
            <select name="product_id" id="filter_product" class="form-select form-select-sm" style="min-width:240px;">
                <option value="">All products</option>
                @foreach($products as $p)
                    <option value="{{ $p->id }}" @selected($filterProductId === $p->id)>{{ $p->name }} ({{ $p->sku }})</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label small mb-1" for="filter_rating">Rating</label>
            <select name="rating" id="filter_rating" class="form-select form-select-sm" style="width:120px;">
                <option value="">All</option>
                @for($i = 5; $i >= 1; $i--)
                    <option value="{{ $i }}" @selected($filterRating === $i)>{{ $i }} star{{ $i > 1 ? 's' : '' }}</option>
                @endfor
            </select>
        </div>
        <button class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> Filter</button>
        <a href="{{ route('admin.product-reviews.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
    </form>
</div></div>

<div class="card"><div class="card-body">
<table id="reviews-table" class="table align-middle w-100">
    <thead>
    <tr>
        <th>Product</th>
        <th>Customer</th>
        <th>Rating</th>
        <th>Review</th>
        <th data-dt-no-export>Verified</th>
        <th>Date</th>
        <th data-dt-no-export class="text-end">Actions</th>
    </tr>
    </thead>
    <tbody>
    @forelse($reviews as $r)
        <tr>
            <td>
                @if($r->product)
                    <a href="{{ route('admin.products.edit', $r->product) }}">{{ $r->product->name }}</a>
                    <div class="small text-muted">{{ $r->product->sku }}</div>
                @else
                    <span class="text-muted">Deleted product</span>
                @endif
            </td>
            <td>
                {{ $r->customer?->name ?? '-' }}
                @if($r->customer?->email)<div class="small text-muted">{{ $r->customer->email }}</div>@endif
            </td>
            <td class="text-nowrap" style="color:#ff8c42;">
                @for($i = 1; $i <= 5; $i++)
                    <i class="bi bi-{{ $i <= $r->rating ? 'star-fill' : 'star' }}"></i>
                @endfor
            </td>
            <td>
                @if($r->title)<strong>{{ $r->title }}</strong><br>@endif
                <span class="text-muted">{{ \Illuminate\Support\Str::limit($r->comment, 140) }}</span>
            </td>
            <td>
                @if($r->verified_purchase)
                    <span class="badge text-bg-success">Verified purchase</span>
                @else
                    <span class="badge text-bg-light">Unverified</span>
                @endif
            </td>
            <td class="text-nowrap">{{ $r->created_at?->format('Y-m-d H:i') }}</td>
            <td class="text-end text-nowrap">
                @if($r->product)
                    <a href="{{ route('shop.products.show', $r->product) }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                    <a href="{{ route('admin.product-reviews.index', ['product_id' => $r->product_id]) }}" class="btn btn-sm btn-outline-primary" title="Show only this product's reviews"><i class="bi bi-funnel"></i></a>
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="7" class="text-center text-muted py-4">No reviews yet.</td></tr>
    @endforelse
    </tbody>
</table>
</div></div>

@push('scripts')
<script>
$(function () {
    $('#reviews-table').DataTable({
        order: [[5, 'desc']],
        pageLength: 15,
        lengthMenu: [[10, 15, 25, 50, 100, -1], [10, 15, 25, 50, 100, 'All']],
        columnDefs: [
            { targets: [3, 6], orderable: false, searchable: false }
        ],
        layout: {
            topStart: {
                buttons: [
                    { extend: 'copy',  className: 'btn btn-sm btn-primary',   exportOptions: { columns: ':not([data-dt-no-export])' } },
                    { extend: 'csv',   className: 'btn btn-sm btn-success',   filename: 'product-reviews', exportOptions: { columns: ':not([data-dt-no-export])' } },
                    { extend: 'excel', className: 'btn btn-sm btn-success',   filename: 'product-reviews', exportOptions: { columns: ':not([data-dt-no-export])' } },
                    { extend: 'pdf',    className: 'btn btn-sm btn-danger',   filename: 'product-reviews', orientation: 'landscape', pageSize: 'A4', exportOptions: { columns: ':not([data-dt-no-export])' } },
                    { extend: 'print',  className: 'btn btn-sm btn-secondary', exportOptions: { columns: ':not([data-dt-no-export])' } }
                ]
            },
            topEnd: ['pageLength', 'search']
        }
    });
});
</script>
@endpush
@endsection
