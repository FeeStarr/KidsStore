@extends('layouts.admin', ['title' => $coupon->code])
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><span class="badge bg-dark">{{ $coupon->code }}</span> {{ $coupon->name }}</h3>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.coupons.edit', $coupon) }}" class="btn btn-outline-primary">Edit</a>
        @if($coupon->status === 'inactive')
            <form action="{{ route('admin.coupons.activate', $coupon) }}" method="post">
                @csrf
                <button class="btn btn-outline-success">Activate</button>
            </form>
        @else
            <form action="{{ route('admin.coupons.deactivate', $coupon) }}" method="post" data-confirm="Deactivate this coupon? It will stop being applied at checkout.">
                @csrf
                <button class="btn btn-outline-warning">Deactivate</button>
            </form>
        @endif
        <form action="{{ route('admin.coupons.destroy', $coupon) }}" method="post" data-confirm="Archive this coupon? Historical order pricing will be preserved.">
            @csrf @method('DELETE')
            <button class="btn btn-outline-danger">Archive</button>
        </form>
        <a href="{{ route('admin.coupons.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>
</div>

@if(! $coupon->is_active)
    <div class="alert alert-warning">This coupon is {{ $coupon->status }} and will not be applied at checkout.</div>
@endif
@if($coupon->usage_limit !== null && $coupon->usage_count >= $coupon->usage_limit)
    <div class="alert alert-warning">This coupon has reached its global usage limit.</div>
@endif

<div class="row g-3">
    <div class="col-md-6">
        <div class="card mb-3"><div class="card-body">
            <h6>Details</h6>
            <dl class="row mb-0">
                <dt class="col-4">Code</dt><dd class="col-8">{{ $coupon->code }}</dd>
                <dt class="col-4">Discount</dt><dd class="col-8">{{ $coupon->discount_label }}</dd>
                <dt class="col-4">Applies To</dt><dd class="col-8">{{ $coupon->applies_to === 'regular_price_only' ? 'Regular-price products only (excludes deal items)' : 'All eligible products' }}</dd>
                <dt class="col-4">Starts</dt><dd class="col-8">{{ $coupon->starts_at ? $coupon->starts_at->format('M d, Y g:i A') : '—' }}</dd>
                <dt class="col-4">Ends</dt><dd class="col-8">{{ $coupon->ends_at ? $coupon->ends_at->format('M d, Y g:i A') : '—' }}</dd>
                <dt class="col-4">Min Order</dt><dd class="col-8">{{ $coupon->minimum_order_amount !== null ? '₦'.number_format((float) $coupon->minimum_order_amount, 2) : '—' }}</dd>
                <dt class="col-4">Max Discount</dt><dd class="col-8">{{ $coupon->maximum_discount_amount !== null ? '₦'.number_format((float) $coupon->maximum_discount_amount, 2) : '—' }}</dd>
                <dt class="col-4">Status</dt><dd class="col-8"><span class="badge {{ $coupon->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $coupon->status }}</span></dd>
                <dt class="col-4">Usage</dt>
                <dd class="col-8">
                    {{ $coupon->usage_count }}
                    @if($coupon->usage_limit) / {{ $coupon->usage_limit }} @endif
                    <small class="text-muted d-block">per customer: {{ $coupon->per_customer_limit }}</small>
                </dd>
                <dt class="col-4">Created By</dt><dd class="col-8">{{ $coupon->createdBy?->name ?? '—' }}</dd>
            </dl>
        </div></div>
        @if($coupon->description)
            <div class="card mb-3"><div class="card-body">
                <h6>Description</h6>
                <p class="mb-0">{{ $coupon->description }}</p>
            </div></div>
        @endif
    </div>

    <div class="col-md-6">
        <div class="card"><div class="card-header">Targets</div>
            <div class="card-body p-0">
                @php($hasTargets = $coupon->products->isNotEmpty() || $coupon->variants->isNotEmpty())
                @if(! $hasTargets)
                    <div class="p-3 text-muted">Applies to all products in cart.</div>
                @else
                    <ul class="list-group list-group-flush" style="max-height:420px;overflow-y:auto">
                        @foreach($coupon->products as $p)
                            <li class="list-group-item d-flex align-items-center gap-2">
                                @if($p->primaryImage)
                                    <img src="{{ $p->primaryImage->url }}" style="width:40px;height:40px;object-fit:cover;border-radius:.35rem">
                                @endif
                                <div>
                                    <a href="{{ route('admin.products.show', $p) }}" class="text-decoration-none">{{ $p->name }}</a>
                                    <small class="text-muted d-block">Product</small>
                                </div>
                            </li>
                        @endforeach
                        @foreach($coupon->variants as $v)
                            <li class="list-group-item d-flex align-items-center gap-2">
                                <div>
                                    {{ $v->product?->name }}
                                    <small class="text-muted d-block">{{ $v->name ?: 'Default variant' }} (variant) — ₦{{ number_format((float) $v->selling_price, 2) }}</small>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.querySelectorAll('form[data-confirm]').forEach(f => {
    f.addEventListener('submit', e => {
        if (!confirm(f.dataset.confirm)) e.preventDefault();
    });
});
</script>
@endpush
@endsection