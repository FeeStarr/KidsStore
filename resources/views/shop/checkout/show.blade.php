@extends('layouts.shop', ['title' => 'Checkout'])
@section('content')

<h3 class="mb-3">Checkout</h3>

<form action="{{ route('shop.checkout.place') }}" method="post" class="row g-4" id="checkout-form">
    @csrf

    <div class="col-md-7">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <h5 class="mb-3">Contact</h5>

            <div class="mb-3">
                <label class="form-label">Name</label>
                <input type="text" class="form-control" value="{{ $customer->name }}" disabled>
            </div>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" class="form-control" value="{{ $customer->email }}" disabled>
            </div>
            <div class="mb-3">
                <label class="form-label">Phone *</label>
                <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror"
                       value="{{ old('phone', $customer->phone) }}" required>
                @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <hr>
            <h5 class="mb-3">Delivery Method</h5>

            <input type="hidden" name="delivery_method" value="pickup">

            <div class="d-flex gap-3 mb-3">
                <div class="form-check delivery-option flex-grow-1 border rounded p-3 border-primary bg-primary-subtle"
                     id="opt-delivery" style="display:none">
                </div>
                <div class="form-check delivery-option flex-grow-1 border rounded p-3 border-primary bg-primary-subtle"
                     id="opt-pickup">
                    <label class="form-check-label fw-semibold" for="dm_pickup">
                        <i class="bi bi-geo-alt me-1"></i> Pick Up
                        <small class="text-muted d-block fw-normal">Collect from a station near you</small>
                    </label>
                </div>
            </div>
            @error('delivery_method')
                <div class="text-danger small mb-2">{{ $message }}</div>
            @enderror

            {{-- Home delivery address --}}
            <div id="section-delivery" style="display:none">
                <label class="form-label">Delivery Address *</label>
                <textarea name="address" rows="3"
                          class="form-control @error('address') is-invalid @enderror"
                          placeholder="Full delivery address">{{ old('address', $customer->address) }}</textarea>
                @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            {{-- Pickup station selection --}}
            <div id="section-pickup">
                <label class="form-label">Choose Pickup Station *</label>
                @if($pickupStations->isEmpty())
                    <div class="alert alert-warning">No pickup stations are currently available. Please choose home delivery.</div>
                @else
                    <div class="row g-2">
                        @foreach($pickupStations as $station)
                            <div class="col-12">
                                <label class="d-block border rounded p-3 station-card {{ old('pickup_station_id') == $station->id ? 'border-primary bg-primary-subtle' : '' }}"
                                       style="cursor:pointer">
                                     <div class="d-flex align-items-start gap-2">
                                             <input type="radio" name="pickup_station_id"
                                                 value="{{ $station->id }}"
                                                 class="form-check-input mt-1 flex-shrink-0"
                                                 {{ old('pickup_station_id') == $station->id ? 'checked' : '' }}>
                                        <div>
                                            <div class="fw-semibold">{{ $station->name }}</div>
                                            <div class="text-muted small">{{ $station->full_address }}</div>
                                            @if($station->phone)
                                                <div class="text-muted small"><i class="bi bi-telephone me-1"></i>{{ $station->phone }}</div>
                                            @endif
                                            @if($station->instructions)
                                                <div class="small text-info mt-1"><i class="bi bi-info-circle me-1"></i>{{ $station->instructions }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </label>
                            </div>
                        @endforeach
                    </div>
                    @error('pickup_station_id')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                @endif
            </div>

            <div class="mt-3">
                <label class="form-label">Order Note <small class="text-muted">(optional)</small></label>
                <textarea name="note" rows="2" class="form-control"
                          placeholder="Any special instructions?">{{ old('note') }}</textarea>
            </div>
        </div></div>
    </div>

    <div class="col-md-5">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <h5 class="mb-3">Order Summary</h5>
            <ul class="list-unstyled mb-3">
                @foreach($items as $line)
                    <li class="d-flex justify-content-between mb-2">
                        <span>
                            {{ $line->product->name }}
                            @if($line->variant->options_label !== 'Default')
                                <small class="text-muted d-block">{{ $line->variant->options_label }}</small>
                            @endif
                            <small class="text-muted">×{{ $line->quantity }}</small>
                        </span>
                        <strong>&#8358;{{ number_format($line->line_total, 2) }}</strong>
                    </li>
                @endforeach
            </ul>
            <dl class="row mb-3">
                <dt class="col-6">Subtotal</dt>
                <dd class="col-6 text-end">&#8358;{{ number_format($subtotal, 2) }}</dd>
                @php
                    $totalQuantity = $items->sum('quantity');
                    $shippingFeePerItem = (float) \App\Models\Setting::get('shipping_fee', 0);
                    $shippingDiscountPct = (float) \App\Models\Setting::get('shipping_discount', 0);
                    $totalShippingBeforeDiscount = $shippingFeePerItem * $totalQuantity;
                    $shippingDiscountAmount = $totalShippingBeforeDiscount * ($shippingDiscountPct / 100);
                    $totalShipping = $totalShippingBeforeDiscount - $shippingDiscountAmount;
                    $totalAmount = $subtotal + $totalShipping;
                @endphp
                <dt class="col-6">Shipping</dt>
                <dd class="col-6 text-end">
                    <span class="text-muted small d-block">&#8358;{{ number_format($shippingFeePerItem, 2) }} × {{ $totalQuantity }} item(s)</span>
                    <strong>&#8358;{{ number_format($totalShipping, 2) }}</strong>
                    @if($shippingDiscountPct > 0)
                        <small class="text-success d-block">-{{ number_format($shippingDiscountPct, 0) }}% discount: -&#8358;{{ number_format($shippingDiscountAmount, 2) }}</small>
                    @endif
                </dd>
                <dt class="col-6 fw-bold">Total Amount</dt>
                <dd class="col-6 text-end fw-bold">&#8358;{{ number_format($totalAmount, 2) }}</dd>
            </dl>
            <div class="mb-3">
                <label class="form-label">Payment Method</label>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="payment_method" id="pm_pay_at_pickup" value="pay_at_pickup" checked>
                    <label class="form-check-label" for="pm_pay_at_pickup">
                        Pay at Pickup
                        <div class="small text-muted mt-1">Pay when you collect your order at the pickup station. Station staff will provide bank details.</div>
                    </label>
                </div>
                @if(false)
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="payment_method" id="pm_pay_now" value="pay_now">
                    <label class="form-check-label" for="pm_pay_now">
                        Pay Now (Bank Transfer)
                        <span class="badge bg-success ms-1">Instant</span>
                        <div class="small text-muted mt-1">A virtual account number will be generated for you to transfer to. Payment is verified automatically.</div>
                    </label>
                </div>
                @endif
            </div>
            </div>
            <button class="btn btn-primary w-100" type="submit">
                <i class="bi bi-bag-check me-1"></i> Place Order
            </button>
            <div class="small text-muted mt-2 text-center">
                Pay when you collect your order at the pickup station.
            </div>
        </div></div>
    </div>
</form>

@push('scripts')
<script>
(function () {
    const secPickup = document.getElementById('section-pickup');
    const optPickup = document.getElementById('opt-pickup');

    // Always show pickup section
    if (secPickup) secPickup.style.display = '';

    // Highlight station cards on select
    document.querySelectorAll('[name="pickup_station_id"]').forEach(radio => {
        radio.addEventListener('change', () => {
            document.querySelectorAll('.station-card').forEach(c => {
                c.classList.remove('border-primary', 'bg-primary-subtle');
            });
            radio.closest('.station-card')?.classList.add('border-primary', 'bg-primary-subtle');
        });
    });

    // Clicking the whole option card selects the radio
    document.querySelectorAll('.station-card').forEach(card => {
        card.addEventListener('click', () => {
            const radio = card.querySelector('input[type="radio"]');
            if (radio) { radio.checked = true; radio.dispatchEvent(new Event('change')); }
        });
    });
})();
</script>
@endpush

@endsection
