@extends('layouts.admin', ['title' => 'Pickup Payouts'])
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Pickup Payouts</h3>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.pickup-payouts.records') }}" class="btn btn-sm btn-outline-primary">View Ledger</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="alert alert-info small mb-3">
            <i class="bi bi-info-circle me-1"></i>
            Commission is <strong>10% of unit_price × quantity</strong> for each item marked as <strong>"picked up"</strong> by the station.
        </div>

        <table class="table table-sm">
            <thead>
                <tr>
                    <th>Station</th>
                    <th class="text-end">Total Earned</th>
                    <th class="text-end">Paid Out</th>
                    <th class="text-end">Balance Due</th>
                    <th class="text-center">Items Picked Up</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($summary as $s)
                    <tr>
                        <td>
                            <strong>{{ $s['station']->name }}</strong>
                            @if(! $s['station']->is_available)
                                <span class="badge bg-danger ms-1">Unavailable</span>
                            @endif
                        </td>
                        <td class="text-end">₦{{ number_format($s['total_earned'], 2) }}</td>
                        <td class="text-end">₦{{ number_format($s['total_paid_out'], 2) }}</td>
                        <td class="text-end">
                            <span class="{{ $s['balance_due'] > 0 ? 'text-warning fw-bold' : 'text-success' }}">
                                ₦{{ number_format($s['balance_due'], 2) }}
                            </span>
                        </td>
                        <td class="text-center">{{ $s['item_count'] }}</td>
                        <td class="text-end">
                            <a href="{{ route('admin.pickup-payouts.show', $s['station']) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-eye me-1"></i>View
                            </a>
                            <a href="{{ route('admin.pickup-payouts.records', ['station_id' => $s['station']->id]) }}" class="btn btn-sm btn-outline-primary ms-1">
                                <i class="bi bi-clock-history me-1"></i>Ledger
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                            No pickup stations found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if($summary->isNotEmpty())
                <tfoot>
                    <tr class="table-light fw-bold">
                        <td>Total</td>
                        <td class="text-end">₦{{ number_format($summary->sum('total_earned'), 2) }}</td>
                        <td class="text-end">₦{{ number_format($summary->sum('total_paid_out'), 2) }}</td>
                        <td class="text-end">₦{{ number_format($summary->sum('balance_due'), 2) }}</td>
                        <td class="text-center">{{ $summary->sum('item_count') }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection
