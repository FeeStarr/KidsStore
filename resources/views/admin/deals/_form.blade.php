@php
    $isEdit = isset($deal);
    $action = $isEdit ? route('admin.deals.update', $deal) : route('admin.deals.store');
    $selectedIds = $isEdit
        ? $deal->products->pluck('id')->map(fn ($id) => (string) $id)->all()
        : array_map('strval', old('product_ids', []));
    $discountType = old('discount_type', $deal->discount_type ?? 'percentage');
    $discountValue = old('discount_value', $deal->discount_value ?? '');
    $startsAt = old('starts_at', $deal->starts_at ?? '');
    $endsAt = old('ends_at', $deal->ends_at ?? '');
    $maxUses = old('max_uses', $deal->max_uses ?? '');
    $isFeatured = (bool) old('is_featured', $deal->is_featured ?? false);
@endphp
<form method="post" action="{{ $action }}" enctype="multipart/form-data" id="deal-form">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <div class="row g-3">
        <div class="col-md-8">
            <div class="card mb-3">
                <div class="card-header">Basic Information</div>
                <div class="card-body">
                    <div class="mb-3"><label class="form-label">Deal Name *</label>
                        <input name="title" class="form-control" value="{{ old('title', $deal->title ?? '') }}" required></div>
                    <div class="mb-3"><label class="form-label">Deal Description</label>
                        <textarea name="description" rows="3" class="form-control">{{ old('description', $deal->description ?? '') }}</textarea></div>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Banner Image</label>
                            <input type="file" name="banner_image" accept="image/*" class="form-control">
                            @if(!empty($deal->banner_image))
                                <div class="mt-2"><img src="{{ asset('storage/'.$deal->banner_image) }}" style="max-height:80px" class="rounded border" loading="lazy" decoding="async"></div>
                            @endif
                        </div>
                        <div class="col-md-6"><label class="form-label">Thumbnail Image</label>
                            <input type="file" name="thumbnail_image" accept="image/*" class="form-control">
                            @if(!empty($deal->thumbnail_image))
                                <div class="mt-2"><img src="{{ asset('storage/'.$deal->thumbnail_image) }}" style="max-height:80px" class="rounded border" loading="lazy" decoding="async"></div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Discount</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Discount Type *</label>
                            <select name="discount_type" id="discountType" class="form-select" required>
                                <option value="percentage"   @selected($discountType === 'percentage')>Percentage (%)</option>
                                <option value="fixed_amount" @selected($discountType === 'fixed_amount')>Fixed Amount (₦ off)</option>
                                <option value="fixed_price"  @selected($discountType === 'fixed_price')>Fixed Price (deal price)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" id="discountValueLabel">Discount Value *</label>
                            <input type="number" step="0.01" min="0" name="discount_value" id="discountValue"
                                   value="{{ $discountValue }}" class="form-control" required>
                        </div>
                        <div class="col-md-4 align-self-end">
                            <small class="text-muted" id="discountHint">
                                e.g. 20 → 20% off original price.
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Schedule</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Start Date/Time *</label>
                            <input type="datetime-local" name="starts_at" class="form-control"
                                   value="{{ $startsAt ? \Illuminate\Support\Carbon::parse($startsAt)->format('Y-m-d\TH:i') : '' }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Date/Time *</label>
                            <input type="datetime-local" name="ends_at" class="form-control"
                                   value="{{ $endsAt ? \Illuminate\Support\Carbon::parse($endsAt)->format('Y-m-d\TH:i') : '' }}" required>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card mb-3">
                <div class="card-header">Settings</div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input type="checkbox" name="is_featured" value="1" class="form-check-input" id="dealFeatured" {{ $isFeatured ? 'checked' : '' }}>
                        <label class="form-check-label" for="dealFeatured">Featured Deal</label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Maximum Uses</label>
                        <input type="number" min="1" name="max_uses" value="{{ $maxUses }}" class="form-control" placeholder="Unlimited">
                        <div class="form-text">Leave blank for unlimited uses.</div>
                    </div>
                    @if($isEdit)
                        <div class="mb-3">
                            <label class="form-label">Current Uses</label>
                            <input type="text" class="form-control" value="{{ $deal->current_uses }}" disabled>
                        </div>
                    @endif
                </div>
            </div>

            <button class="btn btn-primary w-100">{{ $isEdit ? 'Update Deal' : 'Create Deal' }}</button>
            <a href="{{ route('admin.deals.index') }}" class="btn btn-link w-100">Cancel</a>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Products <span class="text-muted small">(required — the deal applies to all selected products)</span></div>
        <div class="card-body">
            <div class="mb-3">
                <input type="search" id="dealProductSearch" class="form-control" placeholder="Search products...">
            </div>
            <div class="row g-2" id="dealProductList" style="max-height:420px;overflow-y:auto">
                @foreach($products as $p)
                    <div class="col-md-4 deal-product-item" data-name="{{ strtolower($p->name) }}">
                        <div class="form-check border rounded px-3 py-2 h-100">
                            <input class="form-check-input deal-product-check" type="checkbox" name="product_ids[]"
                                   value="{{ $p->id }}" id="dp-{{ $p->id }}"
                                   @checked(in_array((string) $p->id, $selectedIds, true))>
                            <label class="form-check-label w-100" for="dp-{{ $p->id }}">
                                {{ $p->name }}
                                <small class="text-muted d-block">
                                    ₦{{ number_format((float) ($p->defaultVariant?->selling_price ?? $p->selling_price), 2) }}
                                </small>
                            </label>
                        </div>
                    </div>
                @endforeach
            </div>
            @error('product_ids')
                <div class="text-danger small mt-2">{{ $message }}</div>
            @enderror
        </div>
    </div>
</form>

@push('scripts')
<script>
(function () {
    const typeSel   = document.getElementById('discountType');
    const valInput  = document.getElementById('discountValue');
    const valLabel  = document.getElementById('discountValueLabel');
    const valHint   = document.getElementById('discountHint');

    const LABELS = {
        percentage:   { label: 'Discount Value *',       hint: 'e.g. 20 → 20% off original price.' },
        fixed_amount: { label: 'Discount Value (₦) *',   hint: 'e.g. 3000 → ₦3,000 off original price.' },
        fixed_price:  { label: 'Deal Price (₦) *',       hint: 'e.g. 10000 → product sells for ₦10,000.' },
    };

    function syncDiscountUI() {
        const cfg = LABELS[typeSel.value] || LABELS.percentage;
        valLabel.textContent = cfg.label;
        valHint.textContent  = cfg.hint;
    }
    typeSel.addEventListener('change', syncDiscountUI);
    syncDiscountUI();

    const search = document.getElementById('dealProductSearch');
    const items  = document.querySelectorAll('.deal-product-item');
    search.addEventListener('input', () => {
        const q = search.value.trim().toLowerCase();
        items.forEach(el => {
            el.style.display = !q || el.dataset.name.includes(q) ? '' : 'none';
        });
    });
})();
</script>
@endpush
