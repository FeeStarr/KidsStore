@extends('layouts.admin', ['title' => 'Profit Report'])
@section('content')

@php
    $fmt = fn($v) => '₦' . number_format((float) $v, 2);
    $profitClass = fn($v) => ((float) $v) >= 0 ? 'text-success' : 'text-danger';
    $marginPct   = function ($revenue, $profit) {
        $r = (float) $revenue; $p = (float) $profit;
        return $r > 0 ? round(($p / $r) * 100, 1) : 0;
    };
@endphp

<div class="d-flex align-items-center justify-content-between mb-3">
    <h3 class="mb-0"><i class="bi bi-graph-up-arrow"></i> Profit Report</h3>
    <small class="text-muted">Based on confirmed / processing / shipped / ready for pick up / delivered orders.</small>
</div>

<form method="get" class="row g-2 mb-4 align-items-end">
    <div class="col-auto">
        <label class="form-label mb-1">From</label>
        <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm">
    </div>
    <div class="col-auto">
        <label class="form-label mb-1">To</label>
        <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm">
    </div>
    <div class="col-auto">
        <button class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> Apply</button>
        <a href="{{ route('admin.reports.profit') }}" class="btn btn-sm btn-link">Reset</a>
    </div>
    <div class="col-auto ms-auto">
        <span class="badge text-bg-light">
            {{ $from->format('M d, Y') }} &mdash; {{ $to->format('M d, Y') }}
        </span>
    </div>
</form>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Revenue</div>
            <div class="h4 mb-0">{{ $fmt($totals->revenue) }}</div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Cost of Goods Sold</div>
            <div class="h4 mb-0">{{ $fmt($totals->cogs) }}</div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Net Profit</div>
            <div class="h4 mb-0 {{ $profitClass($totals->profit) }}">
                {{ $fmt($totals->profit) }}
                <small class="ms-1">({{ $marginPct($totals->revenue, $totals->profit) }}%)</small>
            </div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Units Sold / Orders</div>
            <div class="h4 mb-0">{{ (int) $totals->units_sold }} <small class="text-muted">/ {{ (int) $totals->order_count }}</small></div>
        </div></div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><i class="bi bi-trophy"></i> Top Products by Profit</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead><tr>
                    <th>Product</th>
                    <th>SKU</th>
                    <th class="text-end">Units</th>
                    <th class="text-end">Revenue</th>
                    <th class="text-end">COGS</th>
                    <th class="text-end">Net Profit</th>
                    <th class="text-end">Margin</th>
                </tr></thead>
                <tbody>
                @forelse($byProduct as $p)
                    <tr>
                        <td>{{ $p->name }}</td>
                        <td><code>{{ $p->sku }}</code></td>
                        <td class="text-end">{{ (int) $p->units_sold }}</td>
                        <td class="text-end">{{ $fmt($p->revenue) }}</td>
                        <td class="text-end">{{ $fmt($p->cogs) }}</td>
                        <td class="text-end {{ $profitClass($p->profit) }} fw-semibold">{{ $fmt($p->profit) }}</td>
                        <td class="text-end {{ $profitClass($p->profit) }}">{{ $marginPct($p->revenue, $p->profit) }}%</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No sales in this period.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-calendar-week"></i> Daily Breakdown</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead><tr>
                    <th>Date</th>
                    <th class="text-end">Units</th>
                    <th class="text-end">Revenue</th>
                    <th class="text-end">COGS</th>
                    <th class="text-end">Net Profit</th>
                    <th class="text-end">Margin</th>
                </tr></thead>
                <tbody>
                @forelse($byDay as $d)
                    <tr>
                        <td>{{ \Illuminate\Support\Carbon::parse($d->day)->format('D, M d, Y') }}</td>
                        <td class="text-end">{{ (int) $d->units_sold }}</td>
                        <td class="text-end">{{ $fmt($d->revenue) }}</td>
                        <td class="text-end">{{ $fmt($d->cogs) }}</td>
                        <td class="text-end {{ $profitClass($d->profit) }} fw-semibold">{{ $fmt($d->profit) }}</td>
                        <td class="text-end {{ $profitClass($d->profit) }}">{{ $marginPct($d->revenue, $d->profit) }}%</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No sales in this period.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<p class="text-muted small mt-3 mb-0">
    <i class="bi bi-info-circle"></i>
    Net profit uses the <strong>landed unit cost snapshotted on each order</strong> at the time it was placed,
    so historical figures don't change when you re-purchase at a new price.
</p>
@endsection
