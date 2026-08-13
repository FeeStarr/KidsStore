@extends('layouts.shop', ['title' => 'Cart'])
@section('content')

<h3 class="mb-3">Your Cart</h3>

@if($items->isEmpty())
    <div class="alert alert-light border text-center py-5">
        Your cart is empty. <a href="{{ route('shop.products.index') }}">Browse products</a>.
    </div>
@else
<div class="card border-0 shadow-sm">
<table class="table align-middle mb-0">
    <thead><tr><th colspan="2">Product</th><th class="text-end">Price</th><th>Qty</th><th class="text-end">Total</th><th></th></tr></thead>
    <tbody>
    @foreach($items as $line)
        @php
            $variant = $line->variant;
            $img = $variant->image ?? $line->product->primaryImage;
            $optsLabel = collect([$variant->colorRef?->name, $variant->sizeRef?->name, $variant->ageRange?->name])->filter()->implode(' / ');
        @endphp
        <tr>
            <td style="width:80px">
                @if($img)
                    <img src="{{ $img->url }}" style="width:64px;height:64px;object-fit:cover;border-radius:.35rem">
                @endif
            </td>
            <td>
                <a href="{{ route('shop.products.show', $line->product) }}" class="text-decoration-none text-dark">
                    <strong>{{ $line->product->name }}</strong>
                </a>
                @if($optsLabel)
                    <div class="small text-muted">{{ $optsLabel }}</div>
                @endif
                @if(!empty($line->age_group))
                    <div class="small text-muted">Age Range: {{ $line->age_group }}</div>
                @endif
                @if(!empty($line->selected_size))
                    <div class="small text-muted">Size: {{ $line->selected_size }}</div>
                @endif
                @if($line->deal)
                    <span class="badge bg-danger mt-1">{{ $line->deal->badge_text }}</span>
                @endif
            </td>
            <td class="text-end">
                @if($line->discount > 0 || $line->deal)
                    <span class="price-old">&#8358;{{ number_format($line->deal ? $line->original_unit_price : $line->unit_price, 2) }}</span><br>
                    <strong class="text-danger">&#8358;{{ number_format($line->net_unit, 2) }}</strong>
                    @if($line->deal && $line->discount_amount > 0)
                        <div class="small text-success">Deal discount: -&#8358;{{ number_format($line->discount_amount, 2) }}</div>
                    @endif
                @else
                    <strong>&#8358;{{ number_format($line->unit_price, 2) }}</strong>
                @endif
            </td>
            <td>
                <form action="{{ route('shop.cart.update', $variant) }}" method="post" class="qty-form d-inline-flex align-items-center" data-cart-form>
                    @csrf @method('PATCH')
                    <input type="hidden" name="line_key" value="{{ $line->line_key }}">
                    <button type="button" class="btn btn-sm btn-outline-secondary qty-dec">&minus;</button>
                    <input type="number" name="quantity" value="{{ $line->quantity }}" min="0"
                           class="form-control form-control-sm text-center mx-1 qty-input"
                           style="width:64px" data-original="{{ $line->quantity }}">
                    <button type="button" class="btn btn-sm btn-outline-secondary qty-inc">+</button>
                    <noscript><button class="btn btn-sm btn-primary ms-1">Update</button></noscript>
                </form>
            </td>
            <td class="text-end fw-bold">&#8358;{{ number_format($line->line_total, 2) }}</td>
            <td>
                <form action="{{ route('shop.cart.remove', $variant) }}" method="post">
                    @csrf @method('DELETE')
                    <input type="hidden" name="line_key" value="{{ $line->line_key }}">
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
            </td>
        </tr>
    @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="4" class="text-end fw-bold">Subtotal</td>
            <td class="text-end fw-bold">&#8358;{{ number_format($subtotal, 2) }}</td>
            <td></td>
        </tr>
        @if($coupon)
            <tr>
                <td colspan="4" class="text-end fw-bold">
                    Coupon <span class="text-success">({{ strtoupper($coupon->code) }})</span>
                </td>
                <td class="text-end fw-bold text-success">
                    -&#8358;{{ number_format($coupon_discount, 2) }}
                </td>
                <td>
                    <form action="{{ route('shop.cart.coupon.remove') }}" method="post">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i> Remove</button>
                    </form>
                </td>
            </tr>
        @endif
    </tfoot>
</table>
</div>

@if($coupon)
    <div class="d-flex justify-content-end mt-2">
        <strong class="text-muted">Estimated total (before shipping):</strong>
        <strong class="ms-2">&#8358;{{ number_format(max(0, $subtotal - $coupon_discount), 2) }}</strong>
    </div>
@endif

@if(! $coupon)
    <div class="card border-0 shadow-sm mt-3">
        <div class="card-body">
            <form action="{{ route('shop.cart.coupon.apply') }}" method="post" class="row g-2 align-items-center">
                @csrf
                <div class="col-auto">
                    <i class="bi bi-ticket-perforated text-primary"></i>
                </div>
                <div class="col-auto">
                    <label class="form-label fw-semibold mb-0">Have a coupon?</label>
                </div>
                <div class="col-sm-4 col-lg-3">
                    <input type="text" name="code" class="form-control" placeholder="Enter code"
                           value="{{ old('code') }}" required>
                </div>
                <div class="col-auto">
                    <button class="btn btn-primary">Apply</button>
                </div>
            </form>

            @if(isset($active_coupons) && $active_coupons->count())
                <div class="mt-3">
                    <div class="text-muted small mb-1"><i class="bi bi-ticket-perforated"></i> Active promo codes:</div>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($active_coupons as $c)
                            <button type="button" class="btn btn-sm btn-outline-danger rounded-pill promo-code"
                                    data-code="{{ $c->code }}">{{ strtoupper($c->code) }}</button>
                        @endforeach
                    </div>
                    <small class="text-muted d-block mt-1">Click a code to fill it in.</small>
                </div>
            @endif
        </div>
    </div>
@endif

<div class="d-flex justify-content-between mt-3">
    <a href="{{ route('shop.products.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Continue shopping</a>
    <div>
        <form action="{{ route('shop.cart.clear') }}" method="post" class="d-inline">
            @csrf @method('DELETE')
            <button class="btn btn-outline-danger">Clear cart</button>
        </form>
        @auth
            <a href="{{ route('shop.checkout.show') }}" class="btn btn-primary">Checkout <i class="bi bi-arrow-right"></i></a>
        @else
            <a href="{{ route('shop.login') }}" class="btn btn-primary">Log in to checkout</a>
        @endauth
    </div>
</div>
@endif

@push('scripts')
<script>
document.querySelectorAll('.promo-code').forEach(btn => {
    btn.addEventListener('click', () => {
        const input = document.querySelector('input[name="code"]');
        if (input) {
            input.value = btn.dataset.code;
            input.focus();
        }
    });
});
document.querySelectorAll('form[data-cart-form]').forEach(form => {
    const input = form.querySelector('.qty-input');
    const inc   = form.querySelector('.qty-inc');
    const dec   = form.querySelector('.qty-dec');

    const submit = () => {
        // Avoid pointless POST if value didn't change
        if (input.value === input.dataset.original) return;
        form.submit();
    };

    inc.addEventListener('click', () => {
        input.value = (parseInt(input.value || '0', 10) + 1);
        submit();
    });
    dec.addEventListener('click', () => {
        input.value = Math.max(0, parseInt(input.value || '0', 10) - 1);
        submit();
    });
    input.addEventListener('change', submit);
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); submit(); }
    });
});
</script>
@endpush

@endsection
