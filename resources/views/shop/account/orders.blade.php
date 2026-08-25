@extends('layouts.shop', ['title' => 'My Orders'])
@section('content')
<h3 class="mb-3">My Orders</h3>

<div class="card border-0 shadow-sm">
<table id="orders-table" class="table mb-0 align-middle">
    <thead><tr><th>#</th><th>Reference</th><th>Date</th><th>Status</th><th>Payment</th><th class="text-end">Total</th><th></th></tr></thead>
    <tbody>
    @forelse($orders as $o)
        <tr>
            <td>{{ $loop->iteration }}</td>
            <td><a href="{{ route('shop.account.orders.show', $o) }}">{{ $o->reference }}</a></td>
            <td>{{ $o->order_date?->format('M d, Y g:i A') ?? '-' }}</td>
            <td><span class="badge {{ match($o->status) {
                'delivered' => 'text-bg-success',
                'cancelled' => 'text-bg-danger',
                'pickup window expired' => 'text-bg-danger',
                'ready for pick up' => 'text-bg-warning text-dark',
                'confirmed' => 'text-bg-primary',
                default => 'text-bg-secondary'
            } }}">{{ $o->getStatusLabel() }}</span></td>
            <td><span class="badge text-bg-light">{{ ucfirst($o->payment_status) }}</span></td>
            <td class="text-end">&#8358;{{ number_format($o->total_amount ?: $o->grand_total, 2) }}</td>
            <td class="text-end"><a href="{{ route('shop.account.orders.show', $o) }}" class="btn btn-sm btn-outline-secondary">View</a></td>
        </tr>
    @empty
        <tr><td></td><td></td><td></td><td></td><td></td><td></td><td class="text-center text-muted py-4">You haven't placed any orders yet.</td></tr>
    @endforelse
    </tbody>
</table>
</div>

@push('scripts')
<script>
$(document).ready(function() {
    $('#orders-table').DataTable({
        searching: true,
        paging: true,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'All']],
        info: true,
        ordering: true,
        language: {
            search: '<i class="bi bi-search"></i>',
            searchPlaceholder: 'Search orders...'
        },
        columnDefs: [
            { orderable: false, targets: [6] }
        ],
        order: [[0, 'asc']]
    });
});
</script>
@endpush
@endsection
