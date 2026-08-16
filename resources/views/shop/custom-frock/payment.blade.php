@extends('layouts.shop')

@section('title', 'Pay for Custom Order ' . $customOrder->custom_order_number)

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h1 class="h3 mb-4">Complete Payment</h1>

            <div class="card shadow-sm mb-4">
                <div class="card-header"><h5 class="mb-0">Order Summary</h5></div>
                <div class="card-body">
                    <p><strong>Order:</strong> {{ $customOrder->custom_order_number }}</p>
                    <p><strong>Design:</strong> {{ $customOrder->getCustomizationValue('dress_style') ?: 'Custom Frock' }}</p>

                    <table class="table table-sm mt-3">
                        @foreach ($quote->breakdown ?? [] as $item)
                            <tr>
                                <td>{{ $item['label'] }}</td>
                                <td class="text-end">₦{{ number_format($item['amount'], 2) }}</td>
                            </tr>
                        @endforeach
                        <tr class="fw-bold border-top fs-5">
                            <td>Total</td>
                            <td class="text-end">₦{{ number_format($quote->total, 2) }}</td>
                        </tr>
                    </table>

                    @if ($quote->valid_until)
                        <small class="text-muted">Quote valid until: {{ $quote->valid_until->format('d M Y') }}</small>
                    @endif
                </div>
            </div>

            <div class="card shadow-sm mb-4 border-warning">
                <div class="card-body">
                    <h6 class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i> Return Policy</h6>
                    <p class="small mb-0">Custom frocks are made to order. Returns are only accepted for manufacturing errors or defects.</p>
                </div>
            </div>

            <div class="d-flex gap-3">
                <a href="{{ route('shop.custom-frock.show', $customOrder) }}" class="btn btn-outline-secondary">Back to Order</a>
                <form method="POST" action="{{ route('shop.paystack.initiate', ['order' => $customOrder->order]) }}" class="flex-fill">
                    @csrf
                    <button type="submit" class="btn btn-success btn-lg w-100" id="payBtn">
                        <i class="bi bi-lock me-1"></i> Pay ₦{{ number_format($quote->total, 2) }} with Paystack
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
