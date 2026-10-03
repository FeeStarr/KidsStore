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
    <div class="row g-4 mb-4">
        @if(count($toc) > 1)
            <aside class="col-lg-4">
                <div class="pp-toc card border-0 shadow-sm" style="border-radius:20px;">
                    <div class="card-body p-4">
                        <div class="pp-toc-title"><i class="bi bi-list-ul"></i> On this page</div>
                        <ol class="pp-toc-list mb-0">
                            @foreach($toc as $t)
                                <li><a href="#{{ $t['id'] }}"><span class="pp-toc-num">{{ $t['num'] }}</span>{{ $t['title'] }}</a></li>
                            @endforeach
                        </ol>
                    </div>
                </div>
            </aside>
        @endif

        <div class="{{ count($toc) > 1 ? 'col-lg-8' : 'col-12' }}">
            <div class="card border-0 shadow-sm h-100" style="border-radius:20px;">
                <div class="card-body p-4 p-md-5 pp-doc">
                    @foreach($blocks as $block)
                        @switch($block['type'])
                            @case('title')
                                <div class="pp-doc-title">{{ $block['text'] }}</div>
                                @break
                            @case('meta')
                                <div class="pp-doc-meta"><i class="bi bi-calendar3"></i> Last updated{{ $block['text'] !== '' ? ': ' . $block['text'] : '' }}</div>
                                @break
                            @case('heading')
                                <h3 class="pp-heading" id="{{ $block['id'] }}">
                                    <span class="pp-heading-num">{{ $block['num'] }}</span>
                                    <span>{{ $block['title'] }}</span>
                                </h3>
                                @break
                            @case('subheading')
                                <h4 class="pp-subheading">{{ $block['text'] }}</h4>
                                @break
                            @case('list')
                                <ul class="pp-list">
                                    @foreach($block['items'] as $item)
                                        <li>{{ $item }}</li>
                                    @endforeach
                                </ul>
                                @break
                            @default
                                <p class="pp-p">{{ $block['text'] }}</p>
                        @endswitch
                    @endforeach
                </div>
            </div>
        </div>
    </div>
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
<style>
    .pp-doc { color:#3a2a4a; }
    .pp-doc-title {
        display:flex; align-items:center; gap:.75rem;
        font-size:.8rem; font-weight:700; letter-spacing:2px; text-transform:uppercase; color:#7b68ee;
        margin-bottom:.25rem;
    }
    .pp-doc-title::after { content:""; flex:1; height:2px; border-radius:2px; background:linear-gradient(90deg, #7b68ee, rgba(123,104,238,0)); }
    .pp-doc-meta {
        display:inline-flex; align-items:center; gap:.45rem; margin:.9rem 0 1.25rem;
        background:#f6ecff; color:#7b2d8b; border:1px solid #ead9ff;
        border-radius:50px; padding:.35rem .9rem; font-size:.8rem; font-weight:600;
    }
    .pp-heading {
        display:flex; align-items:center; gap:.75rem; margin:2rem 0 .9rem;
        font-family:'Fredoka', sans-serif; font-size:1.22rem; font-weight:700; color:#241553;
        scroll-margin-top:90px;
    }
    .pp-heading:first-of-type { margin-top:.5rem; }
    .pp-heading-num {
        width:34px; height:34px; flex:0 0 34px; border-radius:50%;
        display:flex; align-items:center; justify-content:center;
        background:linear-gradient(135deg, var(--kid-pink), var(--kid-purple));
        color:#fff; font-size:.95rem; box-shadow:0 6px 14px rgba(155,93,229,.35);
    }
    .pp-subheading {
        font-family:'Fredoka', sans-serif; font-size:1.02rem; font-weight:600; color:#5b3fa8;
        margin:1.4rem 0 .6rem;
    }
    .pp-p { line-height:1.8; margin-bottom:.85rem; color:#3a2a4a; }
    .pp-list { list-style:none; padding:0; margin:0 0 1.1rem; }
    .pp-list li {
        position:relative; padding:.6rem .95rem .6rem 2.5rem; margin-bottom:.5rem;
        border:1px solid #eee8ff; background:#fbfaff; border-radius:12px; line-height:1.65;
    }
    .pp-list li::before {
        content:""; position:absolute; left:1rem; top:1.05rem;
        width:8px; height:8px; border-radius:50%; background:var(--kid-pink);
        box-shadow:0 0 0 4px rgba(255,111,163,.18);
    }
    .pp-toc { position:sticky; top:90px; }
    .pp-toc-title { display:flex; align-items:center; gap:.5rem; font-family:'Fredoka', sans-serif; font-weight:700; color:#241553; margin-bottom:.75rem; }
    .pp-toc-title i { color:var(--kid-pink); }
    .pp-toc-list { list-style:none; margin:0; padding:0; counter-reset:toc; }
    .pp-toc-list li + li { margin-top:.15rem; }
    .pp-toc-list a {
        display:flex; align-items:center; gap:.6rem; padding:.5rem .7rem; border-radius:12px;
        color:#3a2a4a; text-decoration:none; font-size:.92rem; line-height:1.4;
        transition:background .15s ease, color .15s ease;
    }
    .pp-toc-list a:hover, .pp-toc-list a:focus { background:#f6ecff; color:#7b2d8b; }
    .pp-toc-num {
        width:24px; height:24px; flex:0 0 24px; border-radius:50%;
        display:flex; align-items:center; justify-content:center;
        background:#eef4ff; color:#1d4ed8; font-size:.75rem; font-weight:700;
    }
    .pp-toc-list a:hover .pp-toc-num { background:#fff; }
    @media (max-width: 991.98px) { .pp-toc { position:static; } }
</style>
@endpush
