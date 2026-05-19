@extends('layouts.admin', ['title' => 'Vendor Approval'])

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h3 class="mb-0"><i class="bi bi-person-check"></i> Vendor Approval</h3>
        <p class="text-muted mb-0">Review application details and update status.</p>
    </div>
    <a href="{{ route('admin.vendor-approvals.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h5 class="card-title mb-3">Applicant</h5>
                <dl class="row mb-0">
                    <dt class="col-sm-4">Name</dt>
                    <dd class="col-sm-8">{{ $vendorApproval->user?->name ?? 'Unknown' }}</dd>

                    <dt class="col-sm-4">Email</dt>
                    <dd class="col-sm-8">{{ $vendorApproval->user?->email ?? '-' }}</dd>

                    <dt class="col-sm-4">Requested</dt>
                    <dd class="col-sm-8">{{ $vendorApproval->created_at->format('M d, Y h:ia') }}</dd>

                    <dt class="col-sm-4">Status</dt>
                    <dd class="col-sm-8 text-capitalize">{{ $vendorApproval->status }}</dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h5 class="card-title mb-3">Review</h5>
                <form method="post" action="{{ route('admin.vendor-approvals.review', $vendorApproval) }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Decision</label>
                        <select name="status" class="form-select" required>
                            <option value="approved" {{ $vendorApproval->status === 'approved' ? 'selected' : '' }}>Approve</option>
                            <option value="rejected" {{ $vendorApproval->status === 'rejected' ? 'selected' : '' }}>Reject</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" rows="4" class="form-control">{{ old('notes', $vendorApproval->notes) }}</textarea>
                    </div>
                    <button class="btn btn-primary">Save Review</button>
                </form>
            </div>
        </div>

        @if($vendorApproval->reviewed_at)
            <div class="card shadow-sm border-0 mt-3">
                <div class="card-body">
                    <h6 class="mb-2">Last Review</h6>
                    <div class="text-muted small">{{ $vendorApproval->reviewed_at->format('M d, Y h:ia') }}</div>
                    <div class="small">Reviewer: {{ $vendorApproval->reviewer?->name ?? 'Unknown' }}</div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
