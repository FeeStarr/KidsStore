@extends('layouts.shop', ['title' => 'Privacy Policy'])
@section('content')

{{-- Hero --}}
<div class="mb-4 position-relative overflow-hidden" style="background: linear-gradient(135deg, #2b6cf6 0%, #7b68ee 52%, #c77dff 100%); border-radius:24px; padding:2rem 1.5rem; color:#fff;">
    <span class="position-absolute" style="top:-12px; right:12%; font-size:2rem; opacity:.22;">&#10022;</span>
    <span class="position-absolute" style="bottom:8px; right:5%; font-size:1.4rem; opacity:.2;">&#9825;</span>
    <div class="position-relative" style="z-index:1;">
        <div class="d-inline-flex align-items-center gap-2 mb-2 px-3 py-1 bg-white bg-opacity-25 rounded-pill" style="backdrop-filter:blur(6px); font-weight:700; font-size:.78rem;">
            <i class="bi bi-shield-lock"></i> Your data, your control
        </div>
        <h2 class="fw-bold mb-1" style="color:#fff;">Privacy Policy</h2>
        <p class="mb-0" style="color:rgba(255,255,255,.9);">Here's how we collect, use and protect your information at KidsFlairr.</p>
    </div>
</div>

@if($policy)
    @include('shop.partials.policy-document', ['blocks' => $blocks, 'toc' => $toc])
@else
    <div class="card border-0 shadow-sm mb-4" style="border-radius:20px;">
        <div class="card-body p-4 p-md-4">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="d-inline-flex align-items-center justify-content-center" style="width:36px; height:36px; border-radius:50%; background:#eef4ff; color:#2b6cf6;"><i class="bi bi-shield-check"></i></span>
                <h5 class="mb-0 fw-bold">Our promise</h5>
            </div>
            <p class="text-muted mb-0">Our full privacy policy is being updated. In the meantime, here's what always stands:</p>
        </div>
    </div>
@endif

{{-- Always-visible quick reference --}}
<div class="card border-0 shadow-sm" style="border-radius:20px; background:#fff;">
    <div class="card-body p-4 p-md-4">
        <h5 class="fw-bold mb-3"><i class="bi bi-fingerprint me-1" style="color:#7b68ee;"></i> Privacy at a glance</h5>
        <div class="row g-3">
            @php
                $facts = [
                    ['bi-basket me-1', '#f6ecff', '#7b2d8b', 'What we collect', 'Your name, contact details, delivery address and order history - only what\'s needed to sell and deliver your order.'],
                    ['bi-gear me-1', '#eef4ff', '#1d4ed8', 'Why we use it', 'To process orders, take payment, arrange delivery, and send you order updates by email.'],
                    ['bi-eye-slash me-1', '#fff6e0', '#7a4a00', 'What we never do', 'We never sell or rent your personal data to third parties. Payment details are handled by our payment provider.'],
                    ['bi-sliders me-1', '#e6fbf3', '#065f46', 'Your rights', 'Ask us for a copy of your data, correct it, or ask us to delete it by contacting support.'],
                ];
            @endphp
            @foreach($facts as [$icon, $bg, $color, $title, $body])
                <div class="col-md-6">
                    <div class="p-3 rounded-4 h-100" style="background:{{ $bg }}; border:1px solid rgba(0,0,0,.05);">
                        <div class="fw-bold mb-1" style="color:{{ $color }};"><i class="bi {{ $icon }}"></i> {{ $title }}</div>
                        <div class="small text-muted">{{ $body }}</div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="alert mt-3 mb-0 d-flex gap-2 align-items-start" style="background:#e6fbf3; border:1px solid #b8f0de; color:#065f46; border-radius:14px;">
            <i class="bi bi-envelope-heart mt-1"></i>
            <div><strong>Questions?</strong> Email us at <span class="fw-semibold">{{ config('emails.support') }}</span> and we'll tell you exactly what we hold about you.</div>
        </div>
    </div>
</div>
@endsection

@push('styles')
    @include('shop.partials.policy-document-styles')
@endpush
