@php $loc = $loc ?? null; @endphp

<div class="mb-3">
    <label class="form-label">Location Name *</label>
    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
           value="{{ old('name', $loc?->name) }}" required placeholder="e.g. Gwarinpa">
    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label class="form-label">State</label>
    <input type="text" name="state" class="form-control @error('state') is-invalid @enderror"
           value="{{ old('state', $loc?->state) }}" placeholder="e.g. FCT">
</div>

<div class="mb-3">
    <label class="form-label">Description</label>
    <textarea name="description" rows="2" class="form-control @error('description') is-invalid @enderror"
              placeholder="e.g. Within the same district (Wuse, Garki, Jabi, etc.)">{{ old('description', $loc?->description) }}</textarea>
</div>

<div class="mb-3">
    <div class="form-check form-switch">
        <input type="hidden" name="is_active" value="0">
        <input class="form-check-input" type="checkbox" name="is_active" value="1"
               @checked(old('is_active', $loc?->is_active ?? true))>
        <label class="form-check-label">Active</label>
    </div>
</div>
