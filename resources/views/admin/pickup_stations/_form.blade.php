@php $station = $station ?? null; @endphp

<div class="mb-3">
    <label class="form-label">Station Name *</label>
    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
           value="{{ old('name', $station?->name) }}" required>
    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label class="form-label">Address *</label>
    <textarea name="address" rows="2" class="form-control @error('address') is-invalid @enderror"
              required>{{ old('address', $station?->address) }}</textarea>
    @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">City</label>
        <input type="text" name="city" class="form-control @error('city') is-invalid @enderror"
               value="{{ old('city', $station?->city) }}">
    </div>
    <div class="col-md-6">
        <label class="form-label">State</label>
        <input type="text" name="state" class="form-control @error('state') is-invalid @enderror"
               value="{{ old('state', $station?->state) }}">
    </div>
</div>

<div class="row g-3 mt-0">
    <div class="col-md-6">
        <label class="form-label">Phone</label>
        <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror"
               value="{{ old('phone', $station?->phone) }}" placeholder="e.g. 08012345678">
    </div>
    <div class="col-md-6">
        <label class="form-label">
            Commission Fee (%)
            <small class="text-muted">— % of order total owed to station per delivered order</small>
        </label>
        <input type="number" step="0.01" min="0" max="100" name="fee_pct"
               class="form-control @error('fee_pct') is-invalid @enderror"
               value="{{ old('fee_pct', $station?->fee_pct ?? 0) }}">
        @error('fee_pct')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<div class="mb-3 mt-3">
    <label class="form-label">Customer Instructions <small class="text-muted">(shown at checkout)</small></label>
    <textarea name="instructions" rows="3" class="form-control @error('instructions') is-invalid @enderror"
              placeholder="e.g. Open Mon–Sat 9am–6pm. Bring your order confirmation.">{{ old('instructions', $station?->instructions) }}</textarea>
</div>

<div class="mb-3">
    <label class="form-label">
        Staff Portal PIN
        <small class="text-muted">
            @if($station?->access_pin)
                — leave blank to keep current PIN
            @else
                — set a numeric PIN for staff to log into the pickup portal
            @endif
        </small>
    </label>
    <input type="password" name="access_pin" class="form-control @error('access_pin') is-invalid @enderror"
           placeholder="{{ $station?->access_pin ? '••••••' : 'e.g. 1234' }}"
           autocomplete="new-password">
    @error('access_pin')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="form-check form-switch">
    <input type="hidden" name="is_active" value="0">
    <input class="form-check-input" type="checkbox" name="is_active" value="1"
           @checked(old('is_active', $station?->is_active ?? true))>
    <label class="form-check-label">Active (visible to customers)</label>
</div>

