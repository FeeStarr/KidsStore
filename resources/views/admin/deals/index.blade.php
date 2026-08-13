@extends('layouts.admin', ['title' => 'Deals'])
@section('content')
<div class="d-flex justify-content-between mb-3">
    <h3>Deals</h3>
    <a href="{{ route('admin.deals.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New Deal</a>
</div>

<div class="card"><div class="card-body">
@if($deals->isEmpty())
    <p class="text-muted mb-0">No deals yet. Create your first promotion to start showing discounted prices.</p>
@else
    <table class="table align-middle">
        <thead>
        <tr>
            <th>Deal</th>
            <th>Status</th>
            <th class="text-end">Products</th>
            <th>Discount</th>
            <th>Starts</th>
            <th>Ends</th>
            <th class="text-end">Actions</th>
        </tr>
        </thead>
        <tbody>
        @foreach($deals as $deal)
            <tr>
                <td>
                    <strong>{{ $deal->title }}</strong>
                    @if($deal->is_featured)<span class="badge bg-warning text-dark ms-1">Featured</span>@endif
                    @if($deal->is_live)<span class="badge bg-danger">HOT</span>@endif
                    <div class="small text-muted">{{ $deal->slug }}</div>
                </td>
                <td>
                    @php($stored = $deal->computedStatus())
                    <span class="badge
                        @if($stored === 'active') text-bg-success
                        @elseif($stored === 'scheduled') text-bg-primary
                        @elseif($stored === 'expired') text-bg-secondary
                        @elseif($stored === 'cancelled') text-bg-danger
                        @else text-bg-light @endif">
                        {{ $stored === $deal->status ? $deal->status_label : $stored }}
                    </span>
                    @if($stored !== $deal->status)
                        <small class="text-muted d-block">stored: {{ $deal->status }}</small>
                    @endif
                </td>
                <td class="text-end">{{ $deal->products_count }}</td>
                <td>{{ $deal->discount_label }}</td>
                <td>{{ $deal->starts_at ? $deal->starts_at->format('M d, Y g:i A') : '—' }}</td>
                <td>{{ $deal->ends_at ? $deal->ends_at->format('M d, Y g:i A') : '—' }}</td>
                <td class="text-end text-nowrap">
                    <a href="{{ route('admin.deals.show', $deal) }}" class="btn btn-sm btn-outline-secondary">View</a>
                    <a href="{{ route('admin.deals.edit', $deal) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                    @if($deal->status !== 'cancelled' && $deal->status !== 'expired')
                        <form action="{{ route('admin.deals.cancel', $deal) }}" method="post" class="d-inline" data-confirm="Cancel this deal? Discounts will be removed immediately.">
                            @csrf
                            <button class="btn btn-sm btn-outline-danger">Cancel</button>
                        </form>
                    @endif
                    @if($deal->status === 'expired' || $deal->status === 'cancelled')
                        <form action="{{ route('admin.deals.duplicate', $deal) }}" method="post" class="d-inline">
                            @csrf
                            <button class="btn btn-sm btn-outline-secondary">Duplicate</button>
                        </form>
                    @endif
                    <form action="{{ route('admin.deals.destroy', $deal) }}" method="post" class="d-inline" data-confirm="Delete this deal permanently? Historical order pricing will be preserved.">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
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
