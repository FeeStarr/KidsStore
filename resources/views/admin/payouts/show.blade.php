@extends('layouts.admin', ['title' => 'Payouts — '. $pickupStation->name ])
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Payouts — {{ $pickupStation->name }}</h3>
    <div>
        <a href="{{ route('admin.pickup-payouts.index') }}" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>
</div>

<form method="get" action="{{ route('admin.pickup-payouts.show', $pickupStation) }}" class="mb-3 d-flex gap-2">
    <select name="period" class="form-select form-select-sm" style="width:120px">
        <option value="daily" @if($period==='daily') selected @endif>Day</option>
        <option value="weekly" @if($period==='weekly') selected @endif>Week</option>
        <option value="monthly" @if($period==='monthly') selected @endif>Month</option>
        <option value="all" @if($period==='all') selected @endif>All</option>
    </select>
    <input type="date" name="date" value="{{ $date }}" class="form-control form-control-sm" style="width:160px">
    <input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm" style="width:160px" placeholder="From">
    <input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm" style="width:160px" placeholder="To">
    <button class="btn btn-sm btn-primary">Filter</button>
</form>

<div class="mb-3">
    <div class="alert alert-info small">
        Debug: Orders in view: {{ isset($orders) ? $orders->count() : 0 }} — Pending orders: {{ isset($orders) ? $orders->filter(fn($o)=> !$o->items->every(fn($it)=> $it->pickup_station_fee_paid))->count() : 0 }}
    </div>
</div>

<form method="post" action="{{ route('admin.pickup-payouts.mark-paid', $pickupStation) }}">
    @csrf
    <input type="hidden" name="period" value="{{ $period }}">
    <input type="hidden" name="date" value="{{ $date }}">

    <div class="mb-3">
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.querySelectorAll('input[name=\'order_ids[]\']:not(:disabled)').forEach(cb => cb.checked = true)">Select All Pending</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.querySelectorAll('input[name=\'order_ids[]\']').forEach(cb => cb.checked = false)">Deselect All</button>
    </div>

    @if(isset($orders) && $orders->count())
        <table class="table table-sm">
            <thead><tr><th></th><th>Order</th><th>Placed</th><th class="text-end">Grand Total</th><th class="text-end">Fee ({{ $feePct }}%)</th><th>Status</th></tr></thead>
            <tbody>
            @foreach($orders as $o)
                @php $fee = round($o->grand_total * $feePct / 100, 2); @endphp
                <tr>
                    <td><input type="checkbox" name="order_ids[]" value="{{ $o->id }}"></td>
                    <td>{{ $o->reference }}</td>
                    <td>{{ $o->order_date->toDateString() }}</td>
                    <td class="text-end">₦{{ number_format($o->grand_total,2) }}</td>
                    <td class="text-end">₦{{ number_format($fee,2) }}</td>
                    <td>
                        @if($o->items->every(fn($it) => $it->pickup_station_fee_paid))
                            <span class="badge bg-success">Paid</span>
                        @else
                            <span class="badge bg-warning text-dark">Pending</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <div class="alert alert-info">No orders with unpaid pickup fees in the selected period.</div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <label class="form-label">Note (optional)</label>
            <textarea name="note" class="form-control" rows="2" placeholder="e.g., Bank transfer reference, payout date"></textarea>
        </div>
    </div>

    <div class="text-end mt-3">
        <button class="btn btn-primary">Mark Selected as Paid</button>
    </div>
</form>

@endsection
