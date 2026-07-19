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
        <label class="form-label">Email *</label>
        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
               value="{{ old('email', $station?->email) }}" required placeholder="e.g. station@kidsstore.com">
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
    <div class="col-md-6">
        <label class="form-label">Pickup Shipping Fee (per item)
            <small class="text-muted">— flat fee per item charged for pickup at this station (₦)</small>
        </label>
        <input type="number" step="0.01" min="0" name="pickup_shipping_fee"
               class="form-control @error('pickup_shipping_fee') is-invalid @enderror"
               value="{{ old('pickup_shipping_fee', $station?->pickup_shipping_fee) }}">
        @error('pickup_shipping_fee')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<div class="mb-3 mt-3">
    <label class="form-label">Customer Instructions <small class="text-muted">(shown at checkout)</small></label>
    <textarea name="instructions" rows="3" class="form-control @error('instructions') is-invalid @enderror"
              placeholder="e.g. Open Mon–Sat 9am–6pm. Bring your order confirmation.">{{ old('instructions', $station?->instructions) }}</textarea>
</div>

<div class="mb-3 mt-3">
    <label class="form-label fw-bold">Bank Accounts <small class="text-muted">(per-station; used for commission payouts)</small></label>
    <div id="accounts-list">
        @foreach($station?->bankAccounts ?? [] as $idx => $account)
        <div class="account-row mb-2 p-2 border rounded bg-white">
            <input type="hidden" name="accounts[{{ $idx }}][id]" value="{{ $account->id }}">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label form-label-sm">Bank</label>
                    <input type="text" name="accounts[{{ $idx }}][bank_name]" class="form-control form-control-sm"
                           value="{{ old("accounts.{$idx}.bank_name", $account->bank_name) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label form-label-sm">Account name</label>
                    <input type="text" name="accounts[{{ $idx }}][bank_account_name]" class="form-control form-control-sm"
                           value="{{ old("accounts.{$idx}.bank_account_name", $account->bank_account_name) }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label form-label-sm">Account number</label>
                    <input type="text" name="accounts[{{ $idx }}][bank_account_number]" class="form-control form-control-sm"
                           value="{{ old("accounts.{$idx}.bank_account_number", $account->bank_account_number) }}">
                </div>
                <div class="col-md-1">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="default_account" value="{{ $idx }}"
                               {{ $account->is_default ? 'checked' : '' }} title="Make default account">
                        <label class="form-check-label small">Default</label>
                    </div>
                </div>
                <div class="col-md-1 text-end">
                    <button type="button" class="btn btn-sm btn-outline-danger remove-account">Remove</button>
                </div>
                <div class="col-12 mt-2">
                    <label class="form-label form-label-sm">Instructions (optional)</label>
                    <input type="text" name="accounts[{{ $idx }}][instructions]" class="form-control form-control-sm"
                           value="{{ old("accounts.{$idx}.instructions", $account->instructions) }}">
                </div>
            </div>
        </div>
        @endforeach
    </div>
    <button type="button" id="add-account" class="btn btn-sm btn-outline-primary mt-1">+ Add Bank Account</button>
    @error('accounts')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
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

<div class="row g-3 mt-2">
    <div class="col-md-6">
        <div class="form-check form-switch">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" name="is_active" value="1"
                   @checked(old('is_active', $station?->is_active ?? true))>
            <label class="form-check-label">Active (visible to customers)</label>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-check form-switch">
            <input type="hidden" name="is_available" value="0">
            <input class="form-check-input" type="checkbox" name="is_available" value="1"
                   @checked(old('is_available', $station?->is_available ?? true))>
            <label class="form-check-label">Available (accepting orders)</label>
        </div>
    </div>
</div>

<div class="mb-3 mt-3" id="unavailability-reason-group" style="display: {{ ($station?->is_available ?? true) ? 'none' : 'block' }}">
    <label class="form-label">Reason for Unavailability <small class="text-muted">(shown to customers)</small></label>
    <textarea name="unavailability_reason" rows="2" class="form-control @error('unavailability_reason') is-invalid @enderror"
              placeholder="e.g. Temporarily closed for renovation">{{ old('unavailability_reason', $station?->unavailability_reason) }}</textarea>
    @error('unavailability_reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const availableCheck = document.querySelector('input[name="is_available"]');
    const reasonGroup = document.getElementById('unavailability-reason-group');
    if (availableCheck && reasonGroup) {
        availableCheck.addEventListener('change', function() {
            reasonGroup.style.display = this.checked ? 'none' : 'block';
        });
    }
});
</script>
@endpush

@push('scripts')
<script>
document.addEventListener('click', function(e){
    if (e.target && e.target.id === 'add-account') {
        const list = document.getElementById('accounts-list');
        const idx = list.querySelectorAll('.account-row').length;
        const row = document.createElement('div');
        row.className = 'account-row mb-2 p-2 border rounded bg-white';
        row.innerHTML = `
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label form-label-sm">Bank</label>
                    <input type="text" name="accounts[${idx}][bank_name]" class="form-control form-control-sm">
                </div>
                <div class="col-md-4">
                    <label class="form-label form-label-sm">Account name</label>
                    <input type="text" name="accounts[${idx}][bank_account_name]" class="form-control form-control-sm">
                </div>
                <div class="col-md-3">
                    <label class="form-label form-label-sm">Account number</label>
                    <input type="text" name="accounts[${idx}][bank_account_number]" class="form-control form-control-sm">
                </div>
                <div class="col-md-1">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="default_account" value="${idx}" title="Make default account">
                        <label class="form-check-label small">Default</label>
                    </div>
                </div>
                <div class="col-md-1 text-end">
                    <button type="button" class="btn btn-sm btn-outline-danger remove-account">Remove</button>
                </div>
                <div class="col-12 mt-2">
                    <label class="form-label form-label-sm">Instructions (optional)</label>
                    <input type="text" name="accounts[${idx}][instructions]" class="form-control form-control-sm">
                </div>
            </div>`;
        list.appendChild(row);
    }
    if (e.target && e.target.classList && e.target.classList.contains('remove-account')) {
        e.target.closest('.account-row')?.remove();
        document.querySelectorAll('#accounts-list .account-row').forEach((r, i) => {
            r.querySelectorAll('input').forEach(inp => {
                const name = inp.getAttribute('name');
                if (name) {
                    inp.setAttribute('name', name.replace(/accounts\[\d+\]/, `accounts[${i}]`));
                }
            });
        });
    }
});
</script>
@endpush

