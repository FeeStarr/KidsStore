@extends('layouts.pickup-portal', ['title' => 'Return Details — '.session('portal_station_name')])
@section('content')

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">
        <a href="{{ route('pickup-portal.dashboard', ['filter' => 'returns']) }}" class="text-decoration-none">
            <i class="bi bi-arrow-left me-2"></i>Returns
        </a>
        / Return Details
    </h5>
    @if($refundRequest->status === \App\Models\RefundRequest::STATUS_APPROVED)
        <span class="badge bg-warning text-dark fs-6">Awaiting Collection</span>
    @elseif($refundRequest->status === \App\Models\RefundRequest::STATUS_RETURN_COLLECTED)
        <span class="badge bg-success fs-6">Collected</span>
    @endif
</div>

<div class="row g-3">
    {{-- Return Info --}}
    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><i class="bi bi-info-circle me-1"></i>Return Information</div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr>
                        <td class="text-muted" style="width:160px">Order Reference</td>
                        <td><strong>{{ $refundRequest->order?->reference ?? 'N/A' }}</strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Customer</td>
                        <td>{{ $refundRequest->order?->customer?->name ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Customer Phone</td>
                        <td>{{ $refundRequest->order?->customer?->phone ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Customer Email</td>
                        <td>{{ $refundRequest->order?->customer?->email ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Return Reason</td>
                        <td><span class="badge bg-warning text-dark">{{ $refundRequest->reason_label }}</span></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Customer Notes</td>
                        <td>{{ $refundRequest->details ?? 'None' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Refund Amount</td>
                        <td class="fw-bold text-success fs-5">₦{{ number_format($refundRequest->amount, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Approved On</td>
                        <td>{{ $refundRequest->reviewed_at?->format('M d, Y \a\t h:i A') ?? 'N/A' }}</td>
                    </tr>
                </table>
            </div>
        </div>

        {{-- Evidence --}}
        @if($refundRequest->evidence_path || $refundRequest->evidence_video_path)
        <div class="card mt-3">
            <div class="card-header"><i class="bi bi-camera me-1"></i>Customer Evidence</div>
            <div class="card-body">
                @if($refundRequest->evidence_path)
                    <div class="mb-2">
                        <img src="{{ $refundRequest->evidence_url }}" alt="Evidence photo" class="img-fluid rounded" style="max-height:300px;">
                    </div>
                @endif
                @if($refundRequest->evidence_video_path)
                    <div>
                        <video controls style="max-height:300px; max-width:100%;">
                            <source src="{{ $refundRequest->evidence_video_url }}" type="video/mp4">
                            Your browser does not support video.
                        </video>
                    </div>
                @endif
            </div>
        </div>
        @endif
    </div>

    {{-- Item + Actions --}}
    <div class="col-md-4">
        {{-- Item Details --}}
        <div class="card">
            <div class="card-header"><i class="bi bi-box me-1"></i>Item Details</div>
            <div class="card-body text-center">
                @if($refundRequest->orderItem?->product?->image_url)
                    <img src="{{ $refundRequest->orderItem->product->image_url }}" alt="" class="img-thumbnail mb-2" style="max-height:120px;">
                @endif
                <h6 class="mb-1">{{ $refundRequest->orderItem?->product?->name ?? 'N/A' }}</h6>
                @if($refundRequest->orderItem?->variant?->options_label)
                    <p class="text-muted small mb-1">{{ $refundRequest->orderItem->variant->options_label }}</p>
                @endif
                <p class="mb-1">Qty: <strong>{{ $refundRequest->quantity }}</strong></p>
                <p class="mb-0">Price: <strong>₦{{ number_format($refundRequest->orderItem?->line_total ?? 0, 2) }}</strong></p>
            </div>
        </div>

        {{-- Action --}}
        @if($refundRequest->status === \App\Models\RefundRequest::STATUS_APPROVED)
        <div class="card mt-3 border-success">
            <div class="card-header bg-success bg-opacity-10"><i class="bi bi-check-circle me-1"></i>Collect Return</div>
            <div class="card-body text-center">
                <p class="small text-muted mb-3">Confirm that the customer has brought the item to your station.</p>
                <form method="POST" action="{{ route('pickup-portal.returns.collect', $refundRequest) }}">
                    @csrf
                    <button type="submit" class="btn btn-success btn-lg w-100 collect-return-btn"
                            data-item="{{ $refundRequest->orderItem?->product?->name ?? 'this item' }}">
                        <i class="bi bi-check-circle me-1"></i>Mark as Collected
                    </button>
                </form>
            </div>
        </div>
        @elseif($refundRequest->status === \App\Models\RefundRequest::STATUS_RETURN_COLLECTED)
        <div class="card mt-3">
            <div class="card-body text-center text-muted">
                <i class="bi bi-check-circle-fill text-success fs-1 d-block mb-2"></i>
                This return was collected on <strong>{{ $refundRequest->return_collected_at?->format('M d, Y \a\t h:i A') }}</strong>.
                <br>Waiting for admin inspection.
            </div>
        </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
document.querySelectorAll('.collect-return-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const form = this.closest('form');
        const itemName = this.dataset.item;
        Swal.fire({
            title: 'Confirm Return Collection',
            html: `<p>Has the customer brought <strong>${itemName}</strong> for return?</p><p class="text-muted small">Admin and customer care will be notified.</p>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, collected',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#198754',
        }).then(function(result) {
            if (result.isConfirmed) form.submit();
        });
    });
});
</script>
@endpush
@endsection
