@extends('layouts.admin', ['title' => $deal->title])
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">{{ $deal->title }}</h3>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.deals.edit', $deal) }}" class="btn btn-outline-primary">Edit</a>
        @if($deal->status !== 'cancelled' && $deal->status !== 'expired')
            <form action="{{ route('admin.deals.cancel', $deal) }}" method="post" data-confirm="Cancel this deal? Discounts will be removed immediately.">
                @csrf
                <button class="btn btn-outline-danger">Cancel Deal</button>
            </form>
        @endif
        <a href="{{ route('admin.deals.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>
</div>

@php($computed = $deal->computedStatus())
<div class="mb-3">
    <span class="badge
        @if($computed === 'active') text-bg-success
        @elseif($computed === 'scheduled') text-bg-primary
        @elseif($computed === 'expired') text-bg-secondary
        @elseif($computed === 'cancelled') text-bg-danger
        @else text-bg-light @endif fs-6">
        {{ $computed }}
    </span>
    @if($computed !== $deal->status)
        <small class="text-muted">(stored: {{ $deal->status }})</small>
    @endif
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card mb-3"><div class="card-body">
            <h6>Details</h6>
            <dl class="row mb-0">
                <dt class="col-4">Slug</dt><dd class="col-8">{{ $deal->slug }}</dd>
                <dt class="col-4">Discount</dt><dd class="col-8">{{ $deal->discount_label }}</dd>
                <dt class="col-4">Starts</dt><dd class="col-8">{{ $deal->starts_at ? $deal->starts_at->format('M d, Y g:i A') : '—' }}</dd>
                <dt class="col-4">Ends</dt><dd class="col-8">{{ $deal->ends_at ? $deal->ends_at->format('M d, Y g:i A') : '—' }}</dd>
                <dt class="col-4">Featured</dt><dd class="col-8">{{ $deal->is_featured ? 'Yes' : 'No' }}</dd>
                <dt class="col-4">Uses</dt><dd class="col-8">{{ $deal->max_uses ? "{$deal->current_uses} / {$deal->max_uses}" : "{$deal->current_uses} (unlimited)" }}</dd>
                <dt class="col-4">Created By</dt><dd class="col-8">{{ $deal->createdBy?->name ?? '—' }}</dd>
            </dl>
        </div></div>
        @if($deal->description)
            <div class="card mb-3"><div class="card-body">
                <h6>Description</h6>
                <p class="mb-0">{{ $deal->description }}</p>
            </div></div>
        @endif
        @if($deal->banner_image || $deal->thumbnail_image)
            <div class="card mb-3"><div class="card-body">
                <h6>Images</h6>
                <div class="d-flex gap-3">
                    @if($deal->banner_image)<img src="{{ asset('storage/'.$deal->banner_image) }}" style="max-height:120px" class="rounded border">@endif
                    @if($deal->thumbnail_image)<img src="{{ asset('storage/'.$deal->thumbnail_image) }}" style="max-height:120px" class="rounded border">@endif
                </div>
            </div></div>
        @endif
    </div>

    <div class="col-md-6">
        <div class="card"><div class="card-header">Products ({{ $deal->products->count() }})</div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush" style="max-height:420px;overflow-y:auto">
                    @forelse($deal->products as $p)
                        <li class="list-group-item d-flex align-items-center gap-2">
                            @if($p->primaryImage)
                                <img src="{{ $p->primaryImage->url }}" style="width:40px;height:40px;object-fit:cover;border-radius:.35rem">
                            @endif
                            <div>
                                <a href="{{ route('admin.products.show', $p) }}" class="text-decoration-none">{{ $p->name }}</a>
                                <small class="text-muted d-block">
                                    ₦{{ number_format((float) ($p->defaultVariant?->selling_price ?? $p->selling_price), 2) }}
                                    @if($deal->is_live)
                                        → ₦{{ number_format($deal->priceFor((float) ($p->defaultVariant?->selling_price ?? $p->selling_price)), 2) }}
                                    @endif
                                </small>
                            </div>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">No products assigned.</li>
                    @endforelse
                </ul>
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
