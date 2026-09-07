@php $agent = $agent ?? null; @endphp

<div class="mb-3">
    <label class="form-label">Agent Name *</label>
    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
           value="{{ old('name', $agent?->name) }}" required>
    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Contact Name</label>
        <input type="text" name="contact_name" class="form-control @error('contact_name') is-invalid @enderror"
               value="{{ old('contact_name', $agent?->contact_name) }}">
    </div>
    <div class="col-md-6">
        <label class="form-label">Phone</label>
        <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror"
               value="{{ old('phone', $agent?->phone) }}" placeholder="e.g. 08012345678">
    </div>
</div>

<div class="mb-3 mt-3">
    <label class="form-label">Email</label>
    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
           value="{{ old('email', $agent?->email) }}">
</div>

<div class="mb-3">
    <label class="form-label">Notes</label>
    <textarea name="notes" rows="3" class="form-control @error('notes') is-invalid @enderror"
              placeholder="e.g. Same-day delivery across Abuja">{{ old('notes', $agent?->notes) }}</textarea>
</div>

<div class="mb-3">
    <div class="form-check form-switch">
        <input type="hidden" name="is_active" value="0">
        <input class="form-check-input" type="checkbox" name="is_active" value="1"
               @checked(old('is_active', $agent?->is_active ?? true))>
        <label class="form-check-label">Active</label>
    </div>
</div>
