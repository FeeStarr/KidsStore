@extends('layouts.admin', ['title' => 'About Page'])

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">About Page</h3>
    <a href="{{ route('shop.about') }}" target="_blank" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-box-arrow-up-right"></i> Preview
    </a>
</div>

<form method="POST" action="{{ route('admin.about.update') }}">
    @csrf
    @method('PUT')

    <div class="card mb-4">
        <div class="card-header fw-semibold">Hero Banner</div>
        <div class="card-body">
            <div class="text-center mb-3">
                <span style="font-size:4rem;">&#128085;</span>
                <span style="font-size:4rem;">&#128086;</span>
            </div>
            <div class="mb-3">
                <label class="form-label">Title</label>
                <input type="text" name="hero_title" class="form-control @error('hero_title') is-invalid @enderror"
                       value="{{ old('hero_title', $about->hero_title) }}" required>
                @error('hero_title')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label">Subtitle</label>
                <input type="text" name="hero_subtitle" class="form-control @error('hero_subtitle') is-invalid @enderror"
                       value="{{ old('hero_subtitle', $about->hero_subtitle) }}" required>
                @error('hero_subtitle')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header fw-semibold">Content</div>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label">Our Story</label>
                <textarea name="story" rows="5"
                          class="form-control @error('story') is-invalid @enderror"
                          placeholder="Tell visitors about how the store started...">{{ old('story', $about->story) }}</textarea>
                @error('story')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label">Our Mission</label>
                <textarea name="mission" rows="4"
                          class="form-control @error('mission') is-invalid @enderror"
                          placeholder="What drives your business?">{{ old('mission', $about->mission) }}</textarea>
                @error('mission')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header fw-semibold">Contact Information</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                           value="{{ old('email', $about->email) }}" placeholder="hello@example.com">
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror"
                           value="{{ old('phone', $about->phone) }}" placeholder="+234 800 000 0000">
                    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Address</label>
                    <input type="text" name="address" class="form-control @error('address') is-invalid @enderror"
                           value="{{ old('address', $about->address) }}" placeholder="12 Happy Lane, Lagos">
                    @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary px-4">
        <i class="bi bi-check-lg"></i> Save Changes
    </button>
</form>
@endsection
