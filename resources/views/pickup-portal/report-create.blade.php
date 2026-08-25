@extends('layouts.pickup-portal')

@section('title', 'File Report')

@section('content')
<div class="mb-4">
    <a href="{{ route('pickup-portal.reports') }}" class="text-decoration-none">← Back to Reports</a>
    <h4 class="mt-2 mb-0"><i class="bi bi-flag"></i> File a Report</h4>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $e)
                <li>{{ $e }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('pickup-portal.reports.store') }}">
            @csrf
            <div class="mb-3">
                <label for="type" class="form-label fw-bold">Report Type</label>
                <select name="type" id="type" class="form-select" required>
                    <option value="">Select type...</option>
                    <option value="missing_order">Missing Order - order never arrived at station</option>
                    <option value="missing_item">Missing Item - order arrived but item(s) missing</option>
                    <option value="damaged_item">Damaged Item - item arrived damaged</option>
                    <option value="wrong_item">Wrong Item - received wrong item for this order</option>
                    <option value="customer_no_show">Customer No-Show - customer never collected</option>
                    <option value="other">Other</option>
                </select>
            </div>

            <div class="mb-3">
                <label for="order_id" class="form-label fw-bold">Related Order (optional)</label>
                <select name="order_id" id="order_id" class="form-select">
                    <option value="">No specific order</option>
                    @foreach($orders as $order)
                        <option value="{{ $order->id }}">
                            #{{ $order->id }} - {{ $order->customer->name ?? 'Guest' }}
                            ({{ $order->status }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label fw-bold">Description</label>
                <textarea name="description" id="description" class="form-control" rows="4"
                    placeholder="Describe the issue in detail..." required minlength="10" maxlength="2000"></textarea>
                <small class="text-muted">Minimum 10 characters</small>
            </div>

            <button type="submit" class="btn btn-primary" style="border-radius:50px;">
                <i class="bi bi-send me-1"></i> Submit Report
            </button>
        </form>
    </div>
</div>
@endsection
