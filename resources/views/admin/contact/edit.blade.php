@extends('layouts.admin', ['title' => 'Contact Page'])

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-0">Contact Page</h3>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.contact.messages') }}" class="btn btn-sm btn-outline-secondary position-relative">
            <i class="bi bi-inbox"></i> Messages
            @if($unread > 0)
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">{{ $unread }}</span>
            @endif
        </a>
        <a href="{{ route('shop.contact') }}" target="_blank" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-box-arrow-up-right"></i> Preview
        </a>
    </div>
</div>

<form method="POST" action="{{ route('admin.contact.update') }}">
    @csrf
    @method('PUT')

    <div class="card mb-4">
        <div class="card-header fw-semibold">Hero Banner</div>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label">Title</label>
                <input type="text" name="hero_title"
                       class="form-control @error('hero_title') is-invalid @enderror"
                       value="{{ old('hero_title', $contact->hero_title) }}" required>
                @error('hero_title')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label">Subtitle</label>
                <input type="text" name="hero_subtitle"
                       class="form-control @error('hero_subtitle') is-invalid @enderror"
                       value="{{ old('hero_subtitle', $contact->hero_subtitle) }}" required>
                @error('hero_subtitle')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header fw-semibold">Intro Text</div>
        <div class="card-body">
            <textarea name="intro" rows="3"
                      class="form-control @error('intro') is-invalid @enderror"
                      placeholder="Brief intro shown above the contact form...">{{ old('intro', $contact->intro) }}</textarea>
            @error('intro')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header fw-semibold">Contact Details</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" name="email"
                           class="form-control @error('email') is-invalid @enderror"
                           value="{{ old('email', $contact->email) }}" placeholder="hello@example.com">
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone"
                           class="form-control @error('phone') is-invalid @enderror"
                           value="{{ old('phone', $contact->phone) }}" placeholder="+234 800 000 0000">
                    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-8">
                    <label class="form-label">Address</label>
                    <input type="text" name="address"
                           class="form-control @error('address') is-invalid @enderror"
                           value="{{ old('address', $contact->address) }}" placeholder="12 Happy Lane, Lagos">
                    @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Business Hours</label>
                    <input type="text" name="hours"
                           class="form-control @error('hours') is-invalid @enderror"
                           value="{{ old('hours', $contact->hours) }}" placeholder="Mon – Fri: 9 am – 6 pm">
                    @error('hours')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary px-4">
        <i class="bi bi-check-lg"></i> Save Changes
    </button>
</form>
@endsection
