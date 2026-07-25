@extends('layouts.admin', ['title' => 'Payouts — '. $pickupStation->name ])
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Payouts — {{ $pickupStation->name }}</h3>
    <div>
        <a href="{{ route('admin.pickup-payouts.index') }}" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>
</div>

{{-- Payout Summary --}}
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-wallet2 me-2"></i>Earnings Summary</h5>
    </div>
    <div class="card-body">
        <div class="row text-center">
            <div class="col-md-3">
                <div class="border rounded p-3 bg-success bg-opacity-10">
                    <div class="small text-muted">Total Earned</div>
                    <div class="fs-4 fw-bold text-success">₦{{ number_format($payoutSummary['total_earned'], 2) }}</div>
                    <div class="small text-muted">{{ $payoutSummary['item_count'] }} item(s) picked up</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3 bg-primary bg-opacity-10">
                    <div class="small text-muted">Total Paid Out</div>
                    <div class="fs-4 fw-bold text-primary">₦{{ number_format($payoutSummary['total_paid_out'], 2) }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3 {{ $payoutSummary['balance_due'] > 0 ? 'bg-warning bg-opacity-10' : 'bg-success bg-opacity-10' }}">
                    <div class="small text-muted">Balance Due (Pending)</div>
                    <div class="fs-4 fw-bold {{ $payoutSummary['balance_due'] > 0 ? 'text-warning' : 'text-success' }}">
                        ₦{{ number_format($payoutSummary['balance_due'], 2) }}
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="border rounded p-3">
                    <div class="small text-muted">Commission Rate</div>
                    <div class="fs-4 fw-bold">10%</div>
                    <div class="small text-muted">per item</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Commission Breakdown by Order --}}
@if($itemsByOrder->isNotEmpty())
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-list-check me-2"></i>Picked Up Items — Commission Breakdown</h5>
    </div>
    <div class="card-body">
        <form method="post" action="{{ route('admin.pickup-payouts.mark-paid', $pickupStation) }}">
            @csrf

            <div class="mb-3">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.querySelectorAll('input[name=\'order_ids[]\']:not(:disabled)').forEach(cb => cb.checked = true)">Select All</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.querySelectorAll('input[name=\'order_ids[]\']').forEach(cb => cb.checked = false)">Deselect All</button>
            </div>

            @foreach($itemsByOrder as $orderId => $orderData)
                @php
                    $order = $orderData['order'];
                    $allPaid = $orderData['items']->every(fn($i) => $i->pickup_station_fee_paid);
                    $anyUnpaid = $orderData['items']->some(fn($i) => ! $i->pickup_station_fee_paid);
                @endphp
                <div class="card mb-3 {{ $allPaid ? 'border-success' : ($anyUnpaid ? 'border-warning' : '') }}">
                    <div class="card-header d-flex justify-content-between align-items-center {{ $allPaid ? 'bg-success bg-opacity-10' : ($anyUnpaid ? 'bg-warning bg-opacity-10' : '') }}">
                        <div>
                            @if($anyUnpaid)
                                <input type="checkbox" name="order_ids[]" value="{{ $order->id }}">
                            @endif
                            <strong class="ms-2">{{ $order->reference }}</strong>
                            <span class="small text-muted ms-2">{{ $order->order_date?->format('M d, Y') }}</span>
                            @if($allPaid)
                                <span class="badge bg-success ms-2"><i class="bi bi-check-circle me-1"></i>Paid</span>
                            @else
                                <span class="badge bg-warning text-dark ms-2"><i class="bi bi-clock me-1"></i>Pending</span>
                            @endif
                        </div>
                        <div class="fw-bold text-success">
                            Commission: ₦{{ number_format($orderData['commission'], 2) }}
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Variant</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-end">Unit Price</th>
                                    <th class="text-end">Line Total</th>
                                    <th class="text-end">Commission (10%)</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($orderData['items'] as $item)
                                    <tr class="{{ $item->pickup_station_fee_paid ? 'table-success bg-opacity-10' : '' }}">
                                        <td>{{ $item->product?->name }}</td>
                                        <td class="text-muted small">{{ $item->variant?->options_label }}</td>
                                        <td class="text-center">{{ $item->quantity }}</td>
                                        <td class="text-end">₦{{ number_format($item->unit_price, 2) }}</td>
                                        <td class="text-end">₦{{ number_format($item->line_total, 2) }}</td>
                                        <td class="text-end text-success fw-bold">₦{{ number_format($item->commission, 2) }}</td>
                                        <td class="text-center">
                                            @if($item->pickup_station_fee_paid)
                                                <span class="badge bg-success">Paid</span>
                                            @else
                                                <span class="badge bg-warning text-dark">Unpaid</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach

            <div class="card mb-3">
                <div class="card-body">
                    <label class="form-label">Note (optional)</label>
                    <textarea name="note" class="form-control" rows="2" placeholder="e.g., Bank transfer reference, payout date"></textarea>
                </div>
            </div>

            <div class="text-end mt-3">
                <button class="btn btn-primary">
                    <i class="bi bi-check-circle me-1"></i>Mark Selected as Paid
                </button>
            </div>
        </form>
    </div>
</div>
@else
<div class="alert alert-info">
    <i class="bi bi-info-circle me-2"></i>
    No picked-up items found for this station yet. Items will appear here once the station marks them as "picked up".
</div>
@endif

@endsection
