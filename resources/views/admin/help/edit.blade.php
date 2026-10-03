@extends('layouts.admin', ['title' => 'Help & Guide'])
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Help &amp; Guide</h3>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<form method="post" action="{{ route('admin.help.update') }}">
    @csrf @method('PUT')

    <div class="card mb-3">
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label">Guide Content</label>
                <textarea name="help_guide" rows="24" class="form-control @error('help_guide') is-invalid @enderror"
                          placeholder="Write the customer guide here...">{{ old('help_guide', $text) }}</textarea>
                @error('help_guide')<div class="invalid-feedback">{{ $message }}</div>@enderror
                @if($isDefault)
                    <div class="form-text">
                        <i class="bi bi-info-circle me-1"></i>You are showing the built-in guide. Save your own text to replace it.
                    </div>
                @endif
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <div class="p-3 rounded-3 h-100" style="background:#f6ecff; border:1px solid #e9d5ff;">
                        <div class="fw-bold mb-2" style="color:#7b2d8b;"><i class="bi bi-pencil-square me-1"></i> Formatting</div>
                        <ul class="small text-muted mb-0 ps-3">
                            <li>First line in CAPS = page title</li>
                            <li><code>1. Create Your Account</code> = numbered section (builds the "On this page" menu)</li>
                            <li>Lines starting with <code>&bull;</code> = bullet list</li>
                            <li>Any other line = paragraph</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="p-3 rounded-3 h-100" style="background:#fff6e0; border:1px solid #ffe8a3;">
                        <div class="fw-bold mb-2" style="color:#7a4a00;"><i class="bi bi-image me-1"></i> Screenshots</div>
                        <div class="small text-muted">
                            Drop images into <code>public/images/help/</code> named after the section title.
                            Section <strong>3</strong> picks up <code>3.png</code>; section <strong>Sign Up</strong> picks up
                            <code>sign-up.png</code>, <code>sign-up-page.png</code> or <code>Sign up page.png</code>.
                            Several screenshots per section are allowed (they show in filename order); sections without a
                            matching image simply show text.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <button class="btn btn-primary">Save Changes</button>
    <a href="{{ route('shop.help') }}" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-box-arrow-up-right me-1"></i> View page</a>
</form>
@endsection
