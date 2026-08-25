@extends('layouts.admin', ['title' => 'Coupons'])
@section('content')
<div class="d-flex justify-content-between mb-3">
    <h3>Coupons</h3>
    <a href="{{ route('admin.coupons.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New Coupon</a>
</div>

<form method="get" action="{{ route('admin.coupons.index') }}" class="row g-2 mb-3">
    <div class="col-md-4">
        <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Search code or name...">
    </div>
    <div class="col-md-2">
        <select name="status" class="form-select">
            <option value="">All Statuses</option>
            <option value="active" @selected(request('status') === 'active')>Active</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
        </select>
    </div>
    <div class="col-md-2">
        <select name="discount_type" class="form-select">
            <option value="">All Types</option>
            <option value="percentage" @selected(request('discount_type') === 'percentage')>Percentage</option>
            <option value="fixed_amount" @selected(request('discount_type') === 'fixed_amount')>Fixed Amount</option>
            <option value="fixed_price" @selected(request('discount_type') === 'fixed_price')>Fixed Price</option>
        </select>
    </div>
    <div class="col-md-2">
        <button class="btn btn-outline-secondary">Filter</button>
        <a href="{{ route('admin.coupons.index') }}" class="btn btn-link">Reset</a>
    </div>
</form>

<div class="card"><div class="card-body">
@if($coupons->isEmpty())
    <p class="text-muted mb-0">No coupons yet. Create your first coupon to start offering checkout discounts.</p>
@else
    <table class="table align-middle">
        <thead>
        <tr>
            <th>Code</th>
            <th>Name</th>
            <th>Discount</th>
            <th>Applies To</th>
            <th>Status</th>
            <th class="text-end">Usage</th>
            <th>Starts</th>
            <th>Ends</th>
            <th class="text-end">Actions</th>
        </tr>
        </thead>
        <tbody>
        @foreach($coupons as $coupon)
            <tr @if($coupon->trashed()) class="table-secondary" @endif>
                <td><span class="badge bg-dark">{{ $coupon->code }}</span></td>
                <td>{{ $coupon->name }}</td>
                <td>{{ $coupon->discount_label }}</td>
                <td>
                    @if($coupon->products_count > 0 && $coupon->variants_count === 0)
                        {{ $coupon->products_count }} product(s)
                    @elseif($coupon->variants_count > 0)
                        {{ $coupon->variants_count }} variant(s)
                    @else
                        All products
                    @endif
                    @if($coupon->applies_to === 'regular_price_only')
                        <small class="d-block text-muted">regular-price only</small>
                    @endif
                </td>
                <td>
                    @php($live = $coupon->is_active)
                    <span class="badge {{ $live ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $coupon->status }}</span>
                    @if($live && $coupon->trashed())
                        <small class="text-muted d-block">archived</small>
                    @endif
                </td>
                <td class="text-end">
                    {{ $coupon->usage_count }}
                    @if($coupon->usage_limit) / {{ $coupon->usage_limit }} @endif
                </td>
                <td>{{ $coupon->starts_at ? $coupon->starts_at->format('M d, Y g:i A') : '-' }}</td>
                <td>{{ $coupon->ends_at ? $coupon->ends_at->format('M d, Y g:i A') : '-' }}</td>
                <td class="text-end text-nowrap">
                    <a href="{{ route('admin.coupons.show', $coupon) }}" class="btn btn-sm btn-outline-secondary">View</a>
                    @unless($coupon->trashed())
                        <a href="{{ route('admin.coupons.edit', $coupon) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                        @if($coupon->status === 'inactive')
                            <form action="{{ route('admin.coupons.activate', $coupon) }}" method="post" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-outline-success">Activate</button>
                            </form>
                        @else
                            <form action="{{ route('admin.coupons.deactivate', $coupon) }}" method="post" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-outline-warning">Deactivate</button>
                            </form>
                        @endif
                        <form action="{{ route('admin.coupons.destroy', $coupon) }}" method="post" class="d-inline" data-confirm="Archive this coupon? Historical order pricing will be preserved.">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    {{ $coupons->links() }}
@endif
</div></div>

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