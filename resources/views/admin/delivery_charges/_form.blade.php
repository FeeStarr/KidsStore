@php $charge = $charge ?? null; @endphp

<div class="mb-3">
    <label class="form-label">Delivery Agent *</label>
    <select name="delivery_agent_id" class="form-select @error('delivery_agent_id') is-invalid @enderror" required>
        <option value="">-- Select Agent --</option>
        @foreach($agents as $agent)
            <option value="{{ $agent->id }}" {{ old('delivery_agent_id', $charge?->delivery_agent_id) == $agent->id ? 'selected' : '' }}>
                {{ $agent->name }}
            </option>
        @endforeach
    </select>
    @error('delivery_agent_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label class="form-label">Location *</label>
    <select name="delivery_location_id" class="form-select @error('delivery_location_id') is-invalid @enderror" required>
        <option value="">-- Select Location --</option>
        @foreach($locations as $loc)
            <option value="{{ $loc->id }}" {{ old('delivery_location_id', $charge?->delivery_location_id) == $loc->id ? 'selected' : '' }}>
                {{ $loc->name }}{{ $loc->state ? ', ' . $loc->state : '' }}
            </option>
        @endforeach
    </select>
    @error('delivery_location_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label class="form-label">Charge Amount (&#8358;) *</label>
    <input type="number" step="0.01" min="0" name="amount" class="form-control @error('amount') is-invalid @enderror"
           value="{{ old('amount', $charge?->amount ?? 0) }}" required>
    @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label class="form-label">Effective From <small class="text-muted">(optional - for future pricing)</small></label>
    <input type="date" name="effective_from" class="form-control @error('effective_from') is-invalid @enderror"
           value="{{ old('effective_from', $charge?->effective_from?->format('Y-m-d')) }}">
    @error('effective_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <div class="form-check form-switch">
        <input type="hidden" name="is_active" value="0">
        <input class="form-check-input" type="checkbox" name="is_active" value="1"
               @checked(old('is_active', $charge?->is_active ?? true))>
        <label class="form-check-label">Active</label>
    </div>
</div>
