@extends('layouts.admin', ['title' => 'Payouts — '.$pickupStation->name])
@section('content')

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0">Payouts — {{ $pickupStation->name }}</h3>
        <small class="text-muted">Commission rate: <strong>{{ number_format($feePct, 2) }}%</strong> of order total</small>
    </div>
    <a href="{{ route('admin.pickup-stations.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

{{-- Filter form --}}
<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Period</label>
                <select name="period" class="form-select">
                    <option value="daily"   @selected($period === 'daily')>Daily</option>
                    <option value="weekly"  @selected($period === 'weekly')>Weekly</option>
                    <option value="monthly" @selected($period === 'monthly')>Monthly</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">From</label>
                <input type="date" name="from" class="form-control" value="{{ $from }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">To</label>
                <input type="date" name="to" class="form-control" value="{{ $to }}">
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary w-100">Apply</button>
            </div>
        </form>
    </div>
</div>

{{-- Summary cards --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body">
                <div class="text-muted small">Total Delivered Orders</div>
                <div class="fs-2 fw-bold">{{ $aggregate->sum('orders') }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body">
                <div class="text-muted small">Total Sales Value</div>
                <div class="fs-2 fw-bold">₦{{ number_format($grandTotal, 2) }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm text-center border-start border-4 border-success">
            <div class="card-body">
                <div class="text-muted small">Total Fee Owed to Station</div>
                <div class="fs-2 fw-bold text-success">₦{{ number_format($totalFee, 2) }}</div>
            </div>
        </div>
    </div>
</div>

{{-- Breakdown table --}}
<div class="card">
    <div class="card-header">Breakdown by {{ ucfirst($period) }} Period</div>
    @if($aggregate->isEmpty())
        <div class="card-body text-muted text-center py-4">
            No delivered pickup orders found for the selected period.
        </div>
    @else
        <table class="table mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Period</th>
                    <th class="text-end">Orders</th>
                    <th class="text-end">Sales (₦)</th>
                    <th class="text-end">Fee @ {{ number_format($feePct, 2) }}% (₦)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($aggregate as $label => $row)
                    <tr>
                        <td class="fw-semibold">{{ $label }}</td>
                        <td class="text-end">{{ $row['orders'] }}</td>
                        <td class="text-end">{{ number_format($row['total_sales'], 2) }}</td>
                        <td class="text-end text-success fw-semibold">{{ number_format($row['fee_amount'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="fw-bold">
                <tr>
                    <td>Total</td>
                    <td class="text-end">{{ $aggregate->sum('orders') }}</td>
                    <td class="text-end">{{ number_format($grandTotal, 2) }}</td>
                    <td class="text-end text-success">{{ number_format($totalFee, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    @endif
</div>
@endsection
