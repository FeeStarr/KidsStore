@extends('layouts.shop', ['title' => 'Return Policy'])
@section('content')
<div class="mb-4 position-relative overflow-hidden" style="background: linear-gradient(135deg, #7b68ee 0%, #c77dff 52%, #ff6fa3 100%); border-radius:24px; padding:2rem 1.5rem; color:#fff;">
    <span class="position-absolute" style="top:-10px; right:14%; font-size:2rem; opacity:.25;">✦</span>
    <span class="position-absolute" style="bottom:8px; right:5%; font-size:1.4rem; opacity:.22;">♡</span>
    <div class="position-relative" style="z-index:1;">
        <div class="d-inline-flex align-items-center gap-2 mb-2 px-3 py-1 bg-white bg-opacity-25 rounded-pill" style="backdrop-filter:blur(6px); font-weight:700; font-size:.78rem;"> <i class="bi bi-arrow-repeat"></i> Easy Returns</div>
        <h2 class="fw-bold mb-1" style="color:#fff;">Return Policy</h2>
        <p class="mb-0" style="color:rgba(255,255,255,.9);">Shop with confidence — here's how returns & refunds work at KidsFlairr.</p>
    </div>
</div>

@if($policy)
    <div class="card border-0 shadow-sm mb-4" style="border-radius:20px;">
        <div class="card-body p-4 p-md-5">
            <div class="return-policy-content" style="line-height:1.75; color:#3a2a4a;">
                {!! nl2br(e($policy)) !!}
            </div>
        </div>
    </div>
@else
    <div class="card border-0 shadow-sm mb-4" style="border-radius:20px;">
        <div class="card-body p-4 p-md-4">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="d-inline-flex align-items-center justify-content-center" style="width:36px; height:36px; border-radius:50%; background:#fff0f6; color:#ff6fa3;"><i class="bi bi-info-circle-fill"></i></span>
                <h5 class="mb-0 fw-bold">Our promise</h5>
            </div>
            <p class="text-muted">Return policy content is being updated. Below are the standard windows we honour:</p>
            <div class="row g-3 mt-1">
                <div class="col-md-6">
                    <div class="p-3 rounded-4 h-100" style="background:#f6ecff; border:1px solid #e9d5ff;">
                        <div class="fw-bold" style="color:#7b2d8b;"><i class="bi bi-clock-history me-1"></i> General window</div>
                        <div class="small text-muted">Up to <strong>7 days</strong> after delivery for eligible items.</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="p-3 rounded-4 h-100" style="background:#fff6e0; border:1px solid #ffe8a3;">
                        <div class="fw-bold" style="color:#7a4a00;"><i class="bi bi-lightning me-1"></i> Quick cases</div>
                        <div class="small text-muted"><strong>Damaged</strong> 48h • <strong>Missing item</strong> 24h • <strong>Changed mind</strong> 72h</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

{{-- Always-visible quick reference --}}
<div class="card border-0 shadow-sm" style="border-radius:20px; background:#fff;">
    <div class="card-body p-4 p-md-4">
        <h5 class="fw-bold mb-3"><i class="bi bi-stopwatch me-1" style="color:#7b68ee;"></i> Return windows at a glance</h5>
        <div class="row g-2 small">
            @php $rows = [['Wrong item / size / color','5 days'],['Incomplete / not as described','5 days'],['Damaged','48 hours'],['Missing item','24 hours'],['Changed mind','3 days'],['Everything else','7 days']]; @endphp
            @foreach($rows as [$label,$window])
                <div class="col-12 d-flex justify-content-between align-items-center p-2 px-3 rounded-3" style="background:#f8f7ff; border:1px solid #eee8ff;">
                    <span>{{ $label }}</span><span class="badge rounded-pill" style="background:#7b68ee;">{{ $window }}</span>
                </div>
            @endforeach
        </div>
        <div class="alert mt-3 mb-0 d-flex gap-2 align-items-start" style="background:#e6fbf3; border:1px solid #b8f0de; color:#065f46; border-radius:14px;">
            <i class="bi bi-shield-check mt-1"></i>
            <div><strong>Heads up:</strong> Returns need to be requested from your <em>Delivered</em> order. Custom frocks are made-to-order and can't be returned for change of mind. Unpaid <em>Pay Now</em> orders are automatically cancelled after 24 hours.</div>
        </div>
    </div>
</div>
@endsection
