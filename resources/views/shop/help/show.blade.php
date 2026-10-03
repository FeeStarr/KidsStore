@extends('layouts.shop', ['title' => 'Help & Guide'])
@section('content')
<div class="mb-4 position-relative overflow-hidden" style="background: linear-gradient(135deg, #ff8c42 0%, #ff6fa3 55%, #9b5de5 100%); border-radius:24px; padding:2rem 1.5rem; color:#fff;">
    <span class="position-absolute" style="top:-10px; right:14%; font-size:2rem; opacity:.25;">✦</span>
    <span class="position-absolute" style="bottom:8px; right:5%; font-size:1.4rem; opacity:.22;">♡</span>
    <div class="position-relative" style="z-index:1;">
        <div class="d-inline-flex align-items-center gap-2 mb-2 px-3 py-1 bg-white bg-opacity-25 rounded-pill" style="backdrop-filter:blur(6px); font-weight:700; font-size:.78rem;"><i class="bi bi-question-circle"></i> Help &amp; Guide</div>
        <h2 class="fw-bold mb-1" style="color:#fff;">How to use this site</h2>
        <p class="mb-0" style="color:rgba(255,255,255,.9);">Every step, from creating an account to tracking your order and requesting a return.</p>
    </div>
</div>

@if(trim((string) $text) !== '')
    @include('shop.partials.help-document', ['blocks' => $blocks, 'toc' => $toc])
@else
    <div class="card border-0 shadow-sm mb-4" style="border-radius:20px;">
        <div class="card-body p-4 p-md-5 text-center">
            <i class="bi bi-stars display-4 text-muted"></i>
            <h4 class="mt-3 fw-bold">Guide coming soon</h4>
            <p class="text-muted mb-3">We are writing step-by-step guidance for shopping with us.</p>
            <a href="{{ route('shop.contact') }}" class="btn btn-primary">Contact us instead</a>
        </div>
    </div>
@endif

<div class="card border-0 shadow-sm" style="border-radius:20px; background:#fff;">
    <div class="card-body p-4 p-md-5 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <h5 class="fw-bold mb-1"><i class="bi bi-chat-dots me-1" style="color:var(--kid-pink);"></i> Still stuck?</h5>
            <p class="text-muted mb-0">Our team replies within one business day.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('shop.order.lookup') }}" class="btn btn-outline-primary"><i class="bi bi-box-seam me-1"></i> Track Order</a>
            <a href="{{ route('shop.contact') }}" class="btn btn-primary"><i class="bi bi-envelope me-1"></i> Contact Us</a>
        </div>
    </div>
</div>
@endsection

@push('styles')
    @include('shop.partials.policy-document-styles')
    <style>
        .pp-figure { margin: .75rem 0 1.5rem; border-radius:16px; overflow:hidden; border:1px solid #eee8ff; box-shadow:0 8px 24px rgba(155,93,229,.10); background:#fff; }
        .pp-figure img { display:block; width:100%; height:auto; }
        .pp-figure figcaption { font-size:.8rem; color:#8a8fa3; padding:.5rem .9rem; background:#fbfaff; border-top:1px solid #f1ecff; }
    </style>
@endpush
