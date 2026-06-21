@extends('layouts.pickup-portal', ['title' => 'Payouts — '.session('portal_station_name')])
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4>Payouts — {{ session('portal_station_name') }}</h4>
    <div>
        <form method="get" class="d-inline-flex align-items-center gap-2">
            <select name="status" class="form-select form-select-sm">
                <option value="paid" @if(($status ?? 'paid')==='paid') selected @endif>Paid</option>
                <option value="reversed" @if(($status ?? '')==='reversed') selected @endif>Reversed</option>
                <option value="pending" @if(($status ?? '')==='pending') selected @endif>Pending</option>
                <option value="all" @if(($status ?? '')==='all') selected @endif>All</option>
            </select>
            <input type="date" name="from" value="{{ $from ?? '' }}" class="form-control form-control-sm">
            <input type="date" name="to" value="{{ $to ?? '' }}" class="form-control form-control-sm">
            <button class="btn btn-sm btn-primary">Filter</button>
        </form>
        <a href="{{ route('pickup-portal.dashboard') }}" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>
</div>
@if(($status ?? 'pending') === 'pending')
    <form method="POST" action="{{ route('pickup-portal.payouts.markPaid') }}">
        @csrf
        <div class="mb-2 d-flex gap-2 align-items-center">
            <button class="btn btn-sm btn-primary" type="submit">Mark Selected as Paid</button>
            <input type="text" name="note" class="form-control form-control-sm" placeholder="Optional note for payout" style="max-width:360px">
        </div>

        @forelse($pendingOrders as $p)
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="fw-semibold">
                            <input type="checkbox" name="order_ids[]" value="{{ $p->order->id }}"> 
                            <a href="{{ route('pickup-portal.dashboard', ['order_id' => $p->order->id]) }}">{{ $p->order->reference ?? 'Order #'.$p->order->id }}</a>
                            — {{ $p->order->order_date->toDateString() }}
                            <div class="small text-muted">Amount: ₦{{ number_format($p->order->grand_total,2) }}</div>
                        </div>
                        <div class="text-muted">Fee: ₦{{ number_format($p->fee_amount,2) }}</div>
                    </div>
                    <table class="table table-sm mt-2">
                        <thead><tr><th>Item</th><th class="text-end">Qty</th><th class="text-end">Status/Fee</th></tr></thead>
                        <tbody>
                        @foreach($p->order->items as $it)
                            <tr>
                                <td>{{ $it->product->name ?? $it->variant?->name ?? 'Item' }}</td>
                                <td class="text-end">{{ $it->quantity }}</td>
                                <td class="text-end">@if($it->pickup_station_fee_paid) <span class="text-success">Paid</span> @else <span class="text-danger">Pending</span> @endif</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <div class="card"><div class="card-body">No pending payouts found.</div></div>
        @endforelse

        @if($pendingOrders instanceof \Illuminate\Contracts\Pagination\Paginator || $pendingOrders instanceof \Illuminate\Pagination\LengthAwarePaginator)
            <div class="mt-3">{{ $pendingOrders->appends(request()->query())->links('pagination::bootstrap-5') }}</div>
        @endif
    </form>
@else
    @if($pendingOrders->isNotEmpty())
        <h5 class="mb-2">Pending</h5>
        @foreach($pendingOrders as $p)
            <div class="card mb-3">
                <div class="card-body d-flex justify-content-between">
                    <div><a href="{{ route('pickup-portal.dashboard', ['order_id' => $p->order->id]) }}">{{ $p->order->reference ?? 'Order #'.$p->order->id }}</a></div>
                    <div class="text-muted">Fee: ₦{{ number_format($p->fee_amount,2) }} — Amount: ₦{{ number_format($p->order->grand_total,2) }}</div>
                </div>
            </div>
        @endforeach
    @endif

    <div class="card mb-3">
        <div class="card-body p-0">
            <table id="payouts-table" class="table table-sm mb-0" style="width:100%">
                <thead>
                    <tr><th>#</th><th>Reference</th><th>Orders</th><th>Date</th><th class="text-end">Amount</th><th class="text-center">Status</th><th>Note</th><th></th></tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
@endif

@endsection

@push('styles')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
@endpush

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // client-side init for small item tables
            document.querySelectorAll('.datatable').forEach(function (tbl) {
                try { $(tbl).DataTable({ paging: true, searching: true, info: false, lengthChange: false, pageLength: 10 }); } catch(e){}
            });

            // Server-side DataTable for payouts
            var dashboardUrl = '{{ route('pickup-portal.dashboard') }}';

            var payoutsTable = $('#payouts-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route('pickup-portal.payouts.data') }}',
                    data: function(d) {
                        d.status = $('select[name=status]').val();
                        d.from = $('input[name=from]').val();
                        d.to = $('input[name=to]').val();
                    }
                },
                columns: [
                    { data: null, orderable: false, searchable: false, render: function(data,type,row,meta){ return meta.row + meta.settings._iDisplayStart + 1; } },
                    { data: 'reference' },
                    { data: 'orders', orderable: false, searchable: false, render: function(data){
                        if (!data) return '';
                        try {
                            var links = data.map(function(o){
                                var ref = $('<div>').text(o.reference).html();
                                return '<a href="'+ dashboardUrl + '?order_id='+ encodeURIComponent(o.id) +'">'+ ref +'</a>';
                            });
                            return links.join(', ');
                        } catch(e) { return '' }
                    } },
                    { data: 'date' },
                    { data: 'amount', className: 'text-end' },
                    { data: 'is_reversed', className: 'text-center', render: function(d){ return d ? '<span class="text-danger">Reversed</span>' : '<span class="text-success">Paid</span>'; } },
                    { data: 'note' },
                    { data: null, orderable: false, searchable: false, render: function(data){ return '<button class="btn btn-sm btn-outline-secondary toggle-items">Details</button>'; } }
                ],
                order: [[3,'desc']],
                pageLength: 15,
            });

            // show items in child row
            $('#payouts-table tbody').on('click', 'button.toggle-items', function(){
                var tr = $(this).closest('tr');
                var row = payoutsTable.row(tr);
                if (row.child.isShown()) { row.child.hide(); tr.removeClass('shown'); }
                else {
                    var items = row.data().items || [];
                    var html = '<table class="table table-sm mb-0"><thead><tr><th>Order</th><th class="text-end">Order Amount</th><th class="text-end">Fee</th></tr></thead><tbody>';
                    items.forEach(function(it){ html += '<tr><td><a href="'+ dashboardUrl + '?order_id='+ encodeURIComponent(it.order_id) +'">'+ (it.order_reference||'') +'</a></td><td class="text-end">'+ (it.order_amount ? '₦'+Number(it.order_amount).toFixed(2) : '—') +'</td><td class="text-end">₦'+ Number(it.fee_amount).toFixed(2) +'</td></tr>'; });
                    html += '</tbody></table>';
                    row.child(html).show(); tr.addClass('shown');
                }
            });

            // reload table on filter submit
            document.querySelector('form').addEventListener('submit', function(e){ e.preventDefault(); payoutsTable.ajax.reload(); });
        });
    </script>
@endpush
