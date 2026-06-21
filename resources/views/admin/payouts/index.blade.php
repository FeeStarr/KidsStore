@extends('layouts.admin', ['title' => 'Pickup Payouts'])
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Pickup Payouts</h3>
    <form class="d-flex gap-2" method="get" action="">
        <select name="period" class="form-select form-select-sm">
            <option value="daily" @if($period==='daily') selected @endif>Day</option>
            <option value="weekly" @if($period==='weekly') selected @endif>Week</option>
            <option value="monthly" @if($period==='monthly') selected @endif>Month</option>
            <option value="all" @if($period==='all') selected @endif>All</option>
        </select>
        <input type="date" name="date" value="{{ $date }}" class="form-control form-control-sm">
        <input type="date" name="from" value="{{ $from ?? '' }}" class="form-control form-control-sm" placeholder="From">
        <input type="date" name="to" value="{{ $to ?? '' }}" class="form-control form-control-sm" placeholder="To">
        <button class="btn btn-sm btn-primary">Filter</button>
    </form>
</div>

<div class="card"><div class="card-body">
    <div class="mb-3 text-end">
        <a href="{{ route('admin.pickup-payouts.records') }}" class="btn btn-sm btn-outline-primary">View Ledger</a>
    </div>
    <table class="table table-sm">
        <thead><tr><th>Station</th><th class="text-end">Total Due</th><th class="text-end">Pending</th><th class="text-end"># Pending</th><th></th></tr></thead>
        <tbody>
        @foreach($summary as $s)
            <tr>
                <td>{{ $s['station']->name }}</td>
                <td class="text-end">₦{{ number_format($s['total_due'], 2) }}</td>
                <td class="text-end">₦{{ number_format($s['total_pending'], 2) }}</td>
                <td class="text-end">{{ $s['count_pending'] }}</td>
                <td class="text-end">
                    <a href="{{ route('admin.pickup-payouts.show', $s['station']) }}?period={{ $period }}&date={{ $date }}&from={{ $from ?? '' }}&to={{ $to ?? '' }}" class="btn btn-sm btn-outline-secondary">View</a>
                    <a href="{{ route('admin.pickup-payouts.records', array_merge(request()->except('page'), ['station_id' => $s['station']->id])) }}" class="btn btn-sm btn-outline-primary ms-2">Ledger</a>
                    <a href="{{ route('admin.pickup-payouts.index') }}?period=all" class="btn btn-sm btn-outline-info ms-2">Show All Pending</a>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div></div>

@endsection
