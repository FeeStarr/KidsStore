@extends('layouts.shop')

@section('title', 'Custom Frock Order')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h1 class="h3 mb-2">Create a Custom Frock</h1>
            <p class="text-muted mb-4">Upload a design you love and we'll create a custom frock for your little one.</p>

            {{-- Step indicator --}}
            <div class="d-flex justify-content-between mb-4" id="stepIndicator">
                <div class="step-item text-center flex-fill active" data-step="0">
                    <div class="step-circle mx-auto mb-1 bg-primary text-white">1</div>
                    <small class="step-label d-none d-md-inline text-primary fw-bold">Basic Info</small>
                </div>
                <div class="step-item text-center flex-fill" data-step="1">
                    <div class="step-circle mx-auto mb-1 bg-light text-muted">2</div>
                    <small class="step-label d-none d-md-inline text-muted">Upload & Colour</small>
                </div>
                <div class="step-item text-center flex-fill" data-step="2">
                    <div class="step-circle mx-auto mb-1 bg-light text-muted">3</div>
                    <small class="step-label d-none d-md-inline text-muted">Summary</small>
                </div>
            </div>

            <form id="customFrockForm" method="POST" action="{{ route('shop.custom-frock.store') }}" enctype="multipart/form-data">
                @csrf

                <input type="hidden" name="base_product_id" value="{{ $baseProduct?->id }}">

                {{-- Step 1: Basic Info --}}
                <div class="form-step" data-step="0">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header"><h5 class="mb-0">Basic Information</h5></div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="child_name" class="form-label">Child's Name / Nickname <span class="text-danger">*</span></label>
                                    <input type="text" name="child_name" id="child_name" class="form-control" value="{{ old('child_name') }}" maxlength="100" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Gender</label>
                                    <div class="form-control-plaintext fw-bold text-muted"><i class="bi bi-gender-female text-danger me-1"></i> Girl</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="delivery_method" class="form-label">Delivery Method <span class="text-danger">*</span></label>
                                    <div class="d-flex gap-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="delivery_method" id="delivery_home" value="delivery" checked>
                                            <label class="form-check-label" for="delivery_home">Home Delivery</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="delivery_method" id="delivery_pickup" value="pickup">
                                            <label class="form-check-label" for="delivery_pickup">Pickup Station</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 pickup-field" style="display:none">
                                    <label for="pickup_station_id" class="form-label">Pickup Station</label>
                                    <select name="pickup_station_id" id="pickup_station_id" class="form-select">
                                        <option value="">Select station...</option>
                                        @foreach ($pickupStations as $station)
                                            <option value="{{ $station->id }}">{{ $station->name }} - {{ $station->address }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12 delivery-field">
                                    <label for="delivery_address" class="form-label">Delivery Address <span class="text-danger">*</span></label>
                                    <textarea name="delivery_address" id="delivery_address" class="form-control" rows="2" maxlength="500">{{ old('delivery_address') }}</textarea>
                                </div>
                                <div class="col-12">
                                    <label for="customer_notes" class="form-label">Notes (optional)</label>
                                    <textarea name="customer_notes" id="customer_notes" class="form-control" rows="2" maxlength="1000" placeholder="Any special requests...">{{ old('customer_notes') }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Step 2: Upload & Colour --}}
                <div class="form-step d-none" data-step="1">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header"><h5 class="mb-0">Upload Design & Select Size</h5></div>
                        <div class="card-body">
                            @if ($baseProduct)
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-1"></i>
                                    You're customizing: <strong>{{ $baseProduct->name }}</strong>
                                </div>
                            @endif

                            <div class="row g-3">
                                {{-- Reference upload --}}
                                <div class="col-12">
                                    <label class="form-label fw-bold">Reference Design <span class="text-danger">*</span></label>
                                    <input type="file" name="reference_files[]" class="form-control" multiple accept="image/jpeg,image/png,image/webp,application/pdf">
                                    <small class="text-muted">Upload images of the frock you want. Max 5 files, JPG/PNG/WebP/PDF, {{ $fileService->getMaxFileSizeMb() ?? 10 }}MB each.</small>
                                    <div class="alert alert-warning mt-2 small mb-0">
                                        <i class="bi bi-exclamation-triangle me-1"></i>
                                        The final garment may differ slightly due to fabric availability and production methods.
                                    </div>
                                </div>

                                {{-- Age range --}}
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Age Range <span class="text-danger">*</span></label>
                                    <select name="standard_size" class="form-select" required>
                                        <option value="">Select age...</option>
                                        @foreach (\App\Models\AgeRange::where('is_active', true)->orderBy('name')->get() as $age)
                                            <option value="{{ $age->name }}" {{ old('standard_size') === $age->name ? 'selected' : '' }}>{{ $age->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Size preference --}}
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Size Preference</label>
                                    <input type="text" name="child_size" class="form-control" placeholder="e.g. Small, Medium, Large" value="{{ old('child_size') }}">
                                    <small class="text-muted">Optional — our team will confirm during review.</small>
                                </div>

                                {{-- Primary colour --}}
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Primary Colour <span class="text-danger">*</span></label>
                                    @if($colours->count())
                                    <div class="d-flex flex-wrap gap-2 mb-2">
                                        @foreach ($colours as $colour)
                                            <div class="colour-swatch"
                                                 style="width:32px;height:32px;border-radius:50%;background:{{ $colour->hex ?? '#ccc' }};border:2px solid #dee2e6;cursor:pointer;"
                                                 data-colour="{{ $colour->name }}" title="{{ $colour->name }}">
                                            </div>
                                        @endforeach
                                    </div>
                                    @endif
                                    <input type="text" name="primary_colour" id="primary_colour" class="form-control form-control-sm" value="{{ old('primary_colour') }}" placeholder="e.g. Pink, Red, Blue" maxlength="128" required>
                                    <small class="text-muted">Pick a swatch or type a colour name.</small>
                                </div>

                                {{-- Secondary colour --}}
                                <div class="col-md-3">
                                    <label class="form-label fw-bold">Secondary Colour</label>
                                    <input type="text" name="secondary_colour" class="form-control" placeholder="e.g. White" value="{{ old('secondary_colour') }}" maxlength="128">
                                </div>

                                {{-- Accent colour --}}
                                <div class="col-md-3">
                                    <label class="form-label fw-bold">Accent Colour</label>
                                    <input type="text" name="accent_colour" class="form-control" placeholder="e.g. Gold" value="{{ old('accent_colour') }}" maxlength="128">
                                </div>

                                {{-- Colour description --}}
                                <div class="col-12">
                                    <label class="form-label">Colour Description (optional)</label>
                                    <input type="text" name="custom_colour_description" class="form-control" placeholder="Describe your preferred colours..." value="{{ old('custom_colour_description') }}" maxlength="500">
                                </div>

                                {{-- Colour reference images --}}
                                <div class="col-12">
                                    <label class="form-label">Colour Reference Images (optional)</label>
                                    <input type="file" name="colour_files[]" class="form-control" multiple accept="image/jpeg,image/png,image/webp">
                                    <small class="text-muted">Upload images showing your desired colour palette.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Step 3: Summary --}}
                <div class="form-step d-none" data-step="2">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header"><h5 class="mb-0">Order Summary</h5></div>
                        <div class="card-body" id="summaryContent"></div>
                    </div>

                    <div class="card shadow-sm mb-4 border-warning">
                        <div class="card-body">
                            <h6 class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i> Return Policy for Custom Orders</h6>
                            <p class="small mb-2">Custom frocks are made to order and <strong>cannot be returned</strong> for change of mind or incorrect measurements provided by the customer. Returns are accepted for manufacturing defects or errors made by KidsFlairr.</p>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="return_policy_acknowledged" id="return_policy_acknowledged" value="1" required>
                                <label class="form-check-label" for="return_policy_acknowledged">
                                    I confirm the information provided is correct and I accept the <a href="{{ route('shop.return-policy') }}" target="_blank">return policy</a>.
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Navigation --}}
                <div class="d-flex justify-content-between mt-4">
                    <button type="button" class="btn btn-outline-secondary" id="prevBtn" style="display:none">
                        <i class="bi bi-arrow-left me-1"></i> Previous
                    </button>
                    <button type="button" class="btn btn-primary ms-auto" id="nextBtn">
                        Next <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                    <button type="submit" class="btn btn-success ms-auto d-none" id="submitBtn">
                        <i class="bi bi-send me-1"></i> Submit Custom Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('customFrockForm');
    const steps = document.querySelectorAll('.form-step');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const submitBtn = document.getElementById('submitBtn');
    const indicator = document.getElementById('stepIndicator');
    const totalSteps = 3;
    let currentStep = 0;

    function showStep(n) {
        steps.forEach(s => s.classList.add('d-none'));
        steps[n].classList.remove('d-none');

        indicator.querySelectorAll('.step-item').forEach((item, i) => {
            const circle = item.querySelector('.step-circle');
            const label = item.querySelector('.step-label');
            if (i <= n) {
                circle.classList.remove('bg-light', 'text-muted');
                circle.classList.add('bg-primary', 'text-white');
                if (label) { label.classList.remove('text-muted'); label.classList.add('text-primary', 'fw-bold'); }
            } else {
                circle.classList.remove('bg-primary', 'text-white');
                circle.classList.add('bg-light', 'text-muted');
                if (label) { label.classList.remove('text-primary', 'fw-bold'); label.classList.add('text-muted'); }
            }
        });

        prevBtn.style.display = n > 0 ? 'inline-block' : 'none';
        const isLast = n === totalSteps - 1;
        nextBtn.classList.toggle('d-none', isLast);
        submitBtn.classList.toggle('d-none', !isLast);
        if (isLast) buildSummary();
    }

    nextBtn.addEventListener('click', function() {
        if (!validateStep(currentStep)) return;
        if (currentStep < totalSteps - 1) { currentStep++; showStep(currentStep); window.scrollTo(0, 0); }
    });

    prevBtn.addEventListener('click', function() {
        if (currentStep > 0) { currentStep--; showStep(currentStep); window.scrollTo(0, 0); }
    });

    function validateStep(step) {
        const el = steps[step];
        let valid = true;

        el.querySelectorAll('[required]').forEach(f => {
            if (f.offsetParent === null) return;
            if (!f.value || (f.type === 'checkbox' && !f.checked)) {
                f.classList.add('is-invalid');
                valid = false;
            } else {
                f.classList.remove('is-invalid');
            }
        });

        if (!valid) {
            const first = el.querySelector('.is-invalid');
            if (first) first.focus();
        }
        return valid;
    }

    // Delivery method toggle — set initial required state
    const deliveryRadio = document.getElementById('delivery_home');
    if (deliveryRadio && deliveryRadio.checked) {
        document.getElementById('delivery_address').required = true;
    }

    document.querySelectorAll('input[name="delivery_method"]').forEach(r => {
        r.addEventListener('change', function() {
            const isPickup = this.value === 'pickup';
            document.querySelector('.pickup-field').style.display = isPickup ? 'block' : 'none';
            document.querySelector('.delivery-field').style.display = isPickup ? 'none' : 'block';
            document.getElementById('delivery_address').required = !isPickup;
            document.getElementById('pickup_station_id').required = isPickup;
        });
    });

    // Colour swatches
    document.querySelectorAll('.colour-swatch').forEach(swatch => {
        swatch.addEventListener('click', function() {
            document.querySelectorAll('.colour-swatch').forEach(s => s.style.borderColor = '#dee2e6');
            this.style.borderColor = '#0d6efd';
            document.getElementById('primary_colour').value = this.dataset.colour;
        });
    });

    const colourInput = document.getElementById('primary_colour');
    if (colourInput) {
        colourInput.addEventListener('input', function() {
            document.querySelectorAll('.colour-swatch').forEach(s => {
                s.style.borderColor = s.dataset.colour === this.value ? '#0d6efd' : '#dee2e6';
            });
        });
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }

    function buildSummary() {
        const fd = new FormData(form);
        let html = '<h6 class="fw-bold text-primary mb-3">CUSTOM FROCK REQUEST</h6>';
        html += '<table class="table table-sm">';
        const fields = {
            "Child's Name": fd.get('child_name') || '—',
            "Gender": 'Girl',
            "Design Type": 'Upload Reference',
            "Age Range": fd.get('standard_size') || '—',
            "Size Preference": fd.get('child_size') || 'Not specified',
            "Primary Colour": fd.get('primary_colour') || '—',
            "Secondary Colour": fd.get('secondary_colour') || '—',
            "Accent Colour": fd.get('accent_colour') || '—',
            "Colour Description": fd.get('custom_colour_description') || '—',
            "Delivery": fd.get('delivery_method') === 'pickup' ? 'Pickup Station' : 'Home Delivery',
        };
        if (fd.get('customer_notes')) fields['Notes'] = fd.get('customer_notes');

        for (const [k, v] of Object.entries(fields)) {
            html += `<tr><td class="text-muted">${escapeHtml(k)}</td><td class="fw-bold">${escapeHtml(v)}</td></tr>`;
        }
        html += '</table>';
        document.getElementById('summaryContent').innerHTML = html;
    }

    showStep(0);
});
</script>
@endpush
