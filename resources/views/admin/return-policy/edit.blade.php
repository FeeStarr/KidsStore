@extends('layouts.admin', ['title' => 'Return Policy'])
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Return Policy</h3>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@php
    $reasonHints = [];
    foreach (\App\Models\RefundRequest::REASON_WINDOW_SETTINGS as $reason => $settingKey) {
        $reasonHints[$settingKey][] = \App\Models\RefundRequest::REASONS[$reason] ?? $reason;
    }
@endphp

<form method="post" action="{{ route('admin.return-policy.update') }}">
    @csrf @method('PUT')

    <div class="card mb-3">
        <div class="card-body">
            <div class="mb-0">
                <label class="form-label">Return Policy Content</label>
                <textarea name="return_policy" rows="20" class="form-control @error('return_policy') is-invalid @enderror"
                          placeholder="Enter your return policy here...">{{ old('return_policy', $policy) }}</textarea>
                @error('return_policy')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><i class="bi bi-stopwatch me-1"></i>Return Windows</div>
        <div class="card-body">
            <p class="text-muted small mb-3">
                How long after delivery a customer may request a return for each case.
                These values are shown on the public return policy page and enforced on the customer order page.
            </p>
            <div class="row g-3">
                @foreach($windows as $key => $window)
                    <div class="col-md-4">
                        <label class="form-label" for="{{ $key }}">{{ $window['label'] }}</label>
                        <div class="input-group">
                            <input type="number" id="{{ $key }}" name="{{ $key }}"
                                   class="form-control @error($key) is-invalid @enderror"
                                   value="{{ old($key, $window['days']) }}"
                                   min="0.5" max="365" step="0.5">
                            <span class="input-group-text">days</span>
                            @error($key)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="form-text">
                            Default: {{ $window['default'] }} day(s)
                            @if(! empty($reasonHints[$key]))
                                &middot; {{ implode(', ', $reasonHints[$key]) }}
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="form-text mt-3">
                <i class="bi bi-info-circle me-1"></i>Use decimals for partial days (e.g. <strong>0.5</strong> = 12 hours, <strong>2</strong> = 48 hours).
            </div>
        </div>
    </div>

    <button class="btn btn-primary">Save Changes</button>
</form>
@endsection
