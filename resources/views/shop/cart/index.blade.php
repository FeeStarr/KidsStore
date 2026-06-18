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
            $optsLabel = $variant->options_label;
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
                <div class="small text-muted">SKU: {{ $variant->sku }}</div>
            </td>
            <td class="text-end">
                @if($line->discount > 0)
                    <span class="price-old">&#8358;{{ number_format($line->unit_price, 2) }}</span><br>
                    <strong class="text-danger">&#8358;{{ number_format($line->net_unit, 2) }}</strong>
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
    </tfoot>
</table>
</div>

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
