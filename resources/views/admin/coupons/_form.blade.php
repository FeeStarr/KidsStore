@php
    $isEdit = isset($coupon) && $coupon->exists;
    $action = $isEdit ? route('admin.coupons.update', $coupon) : route('admin.coupons.store');
    $selectedProductIds = $isEdit
        ? $coupon->products->pluck('id')->map(fn ($id) => (string) $id)->all()
        : array_map('strval', old('product_ids', []));
    $selectedVariantIds = $isEdit
        ? $coupon->variants->pluck('id')->map(fn ($id) => (string) $id)->all()
        : array_map('strval', old('variant_ids', []));
    $discountType = old('discount_type', $coupon->discount_type ?? 'fixed_amount');
    $discountValue = old('discount_value', $coupon->discount_value ?? '');
    $appliesTo = old('applies_to', $coupon->applies_to ?? 'regular_price_only');
    $status = old('status', $coupon->status ?? 'inactive');
    $startsAt = old('starts_at', $coupon->starts_at ?? '');
    $endsAt = old('ends_at', $coupon->ends_at ?? '');
    $usageLimit = old('usage_limit', $coupon->usage_limit ?? '');
    $minOrder = old('minimum_order_amount', $coupon->minimum_order_amount ?? '');
    $maxDiscount = old('maximum_discount_amount', $coupon->maximum_discount_amount ?? '');
@endphp
<form method="post" action="{{ $action }}">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <div class="row g-3">
        <div class="col-md-8">
            <div class="card mb-3">
                <div class="card-header">Basic Information</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Coupon Code *</label>
                            <input name="code" class="form-control text-uppercase" value="{{ old('code', $coupon->code ?? '') }}" required placeholder="e.g. KIDS500">
                            <div class="form-text">Codes are case-insensitive and stored lowercase.</div>
                            @error('code')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6"><label class="form-label">Coupon Name *</label>
                            <input name="name" class="form-control" value="{{ old('name', $coupon->name ?? '') }}" required></div>
                    </div>
                    <div class="mt-3"><label class="form-label">Description</label>
                        <textarea name="description" rows="2" class="form-control">{{ old('description', $coupon->description ?? '') }}</textarea></div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Discount</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Discount Type *</label>
                            <select name="discount_type" id="cDiscountType" class="form-select" required>
                                <option value="percentage"   @selected($discountType === 'percentage')>Percentage (%)</option>
                                <option value="fixed_amount" @selected($discountType === 'fixed_amount')>Fixed Amount (₦ off)</option>
                                <option value="fixed_price"  @selected($discountType === 'fixed_price')>Fixed Price</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" id="cDiscountValueLabel">Discount Value *</label>
                            <input type="number" step="0.01" min="0" name="discount_value" id="cDiscountValue"
                                   value="{{ $discountValue }}" class="form-control" required>
                        </div>
                        <div class="col-md-4 align-self-end">
                            <small class="text-muted" id="cDiscountHint"></small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Applicability</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Allow on discounted (deal) items? *</label>
                        <select name="applies_to" class="form-select">
                            <option value="regular_price_only" @selected($appliesTo === 'regular_price_only')>
                                Regular-price products only (recommended - coupon does not stack with deals on the same item)
                            </option>
                            <option value="all" @selected($appliesTo === 'all')>
                                All eligible products (coupon also applies to items already on a deal)
                            </option>
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Minimum Order Amount (₦)</label>
                            <input type="number" step="0.01" min="0" name="minimum_order_amount" value="{{ $minOrder }}" class="form-control" placeholder="Optional"></div>
                        <div class="col-md-6"><label class="form-label">Maximum Discount Amount (₦)</label>
                            <input type="number" step="0.01" min="0" name="maximum_discount_amount" value="{{ $maxDiscount }}" class="form-control" placeholder="Optional cap"></div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Schedule</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Start Date/Time</label>
                            <input type="datetime-local" name="starts_at" class="form-control"
                                   value="{{ $startsAt ? \Illuminate\Support\Carbon::parse($startsAt)->format('Y-m-d\TH:i') : '' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Date/Time</label>
                            <input type="datetime-local" name="ends_at" class="form-control"
                                   value="{{ $endsAt ? \Illuminate\Support\Carbon::parse($endsAt)->format('Y-m-d\TH:i') : '' }}">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card mb-3">
                <div class="card-header">Settings</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Status *</label>
                        <select name="status" class="form-select">
                            <option value="active"   @selected($status === 'active')>Active</option>
                            <option value="inactive" @selected($status === 'inactive')>Inactive</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Global Usage Limit</label>
                        <input type="number" min="1" name="usage_limit" value="{{ $usageLimit }}" class="form-control" placeholder="Unlimited">
                        <div class="form-text">Total redemptions across all customers. Each customer may use a coupon once regardless.</div>
                    </div>
                    @if($isEdit)
                        <div class="mb-3">
                            <label class="form-label">Usage Count</label>
                            <input type="text" class="form-control" value="{{ $coupon->usage_count }}" disabled>
                            <div class="form-text">Per-customer limit is fixed at 1.</div>
                        </div>
                    @endif
                </div>
            </div>

            <button class="btn btn-primary w-100">{{ $isEdit ? 'Update Coupon' : 'Create Coupon' }}</button>
            <a href="{{ route('admin.coupons.index') }}" class="btn btn-link w-100">Cancel</a>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Target Products & Variants <span class="text-muted small">(leave empty for cart-wide)</span></div>
        <div class="card-body">
            @error('product_ids')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
            @error('variant_ids')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
            <div class="mb-3">
                <input type="search" id="couponProductSearch" class="form-control" placeholder="Search products...">
            </div>
            <div class="row g-2" id="couponProductList" style="max-height:420px;overflow-y:auto">
                @foreach($products as $p)
                    <div class="col-md-4 coupon-product-item" data-name="{{ strtolower($p->name) }}">
                        <div class="border rounded px-3 py-2 h-100">
                            <div class="form-check">
                                <input class="form-check-input coupon-product-check" type="checkbox" name="product_ids[]"
                                       value="{{ $p->id }}" id="cp-{{ $p->id }}" data-product="{{ $p->id }}"
                                       @checked(in_array((string) $p->id, $selectedProductIds, true))>
                                <label class="form-check-label w-100" for="cp-{{ $p->id }}">
                                    {{ $p->name }}
                                    <small class="text-muted d-block">₦{{ number_format((float) ($p->defaultVariant?->selling_price ?? $p->selling_price), 2) }}</small>
                                </label>
                            </div>
                            @foreach($p->variants as $v)
                                <div class="form-check ms-3 mt-1">
                                    <input class="form-check-input coupon-variant-check" type="checkbox" name="variant_ids[]"
                                           value="{{ $v->id }}" id="cv-{{ $v->id }}" data-variant="{{ $v->id }}"
                                           data-product="{{ $p->id }}"
                                           @checked(in_array((string) $v->id, $selectedVariantIds, true))>
                                    <label class="form-check-label text-muted small" for="cv-{{ $v->id }}">
                                        {{ $v->name ?: 'Default variant' }} - ₦{{ number_format((float) $v->selling_price, 2) }}
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
            <small class="text-muted d-block mt-2">Tip: a checked product applies the coupon to all its variants. Check individual variants to scope more narrowly.</small>
        </div>
    </div>
</form>

@push('scripts')
<script>
(function () {
    const typeSel  = document.getElementById('cDiscountType');
    const valInput = document.getElementById('cDiscountValue');
    const valLabel = document.getElementById('cDiscountValueLabel');
    const valHint  = document.getElementById('cDiscountHint');

    const LABELS = {
        percentage:   { label: 'Discount Value (%) *',   hint: 'e.g. 20 → 20% off eligible items.' },
        fixed_amount: { label: 'Discount Value (₦) *',   hint: 'e.g. 500 → ₦500 off eligible items.' },
        fixed_price:  { label: 'Fixed Price (₦) *',      hint: 'e.g. 10000 → eligible items' },
    };

    function syncUI() {
        const cfg = LABELS[typeSel.value] || LABELS.fixed_amount;
        valLabel.textContent = cfg.label;
        valHint.textContent  = cfg.hint;
    }
    typeSel.addEventListener('change', syncUI);
    syncUI();

    const search = document.getElementById('couponProductSearch');
    const items  = document.querySelectorAll('.coupon-product-item');
    search.addEventListener('input', () => {
        const q = search.value.trim().toLowerCase();
        items.forEach(el => { el.style.display = !q || el.dataset.name.includes(q) ? '' : 'none'; });
    });
})();
</script>
@endpush