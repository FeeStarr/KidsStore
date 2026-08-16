@extends('layouts.shop')

@section('title', 'Start Custom Order')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <h1 class="h3 mb-2">Create a Custom Frock</h1>
            <p class="text-muted mb-4">Create a frock designed specially for your little one. Choose your style, fabric, colour, measurements and other details.</p>

            {{-- Step indicator --}}
            <div class="d-flex justify-content-between mb-4" id="stepIndicator">
                @foreach(['Basic Info', 'Design', 'Customize', 'Fabric & Colour', 'Measurements', 'Instructions', 'Summary'] as $i => $step)
                    <div class="step-item text-center flex-fill {{ $i === 0 ? 'active' : '' }}" data-step="{{ $i }}">
                        <div class="step-circle mx-auto mb-1 {{ $i === 0 ? 'bg-primary text-white' : 'bg-light text-muted' }}">{{ $i + 1 }}</div>
                        <small class="step-label d-none d-md-inline {{ $i === 0 ? 'text-primary fw-bold' : 'text-muted' }}">{{ $step }}</small>
                    </div>
                @endforeach
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
                                    <label for="child_name" class="form-label">Child's Name / Nickname</label>
                                    <input type="text" name="child_name" id="child_name" class="form-control" value="{{ $saved['child_name'] ?? '' }}" maxlength="100">
                                </div>
                                <div class="col-md-3">
                                    <label for="child_age" class="form-label">Age (years)</label>
                                    <input type="number" name="child_age" id="child_age" class="form-control" value="{{ $saved['child_age'] ?? '' }}" min="0" max="18">
                                </div>
                                <div class="col-md-3">
                                    <label for="child_gender" class="form-label">Gender</label>
                                    <select name="child_gender" id="child_gender" class="form-select">
                                        <option value="">Select...</option>
                                        <option value="girl" {{ ($saved['child_gender'] ?? '') === 'girl' ? 'selected' : '' }}>Girl</option>
                                        <option value="boy" {{ ($saved['child_gender'] ?? '') === 'boy' ? 'selected' : '' }}>Boy</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="delivery_method" class="form-label">Delivery Method</label>
                                    <div class="d-flex gap-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="delivery_method" id="delivery_home" value="delivery" {{ ($saved['delivery_method'] ?? 'delivery') === 'delivery' ? 'checked' : '' }}>
                                            <label class="form-check-label" for="delivery_home">Home Delivery</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="delivery_method" id="delivery_pickup" value="pickup" {{ ($saved['delivery_method'] ?? '') === 'pickup' ? 'checked' : '' }}>
                                            <label class="form-check-label" for="delivery_pickup">Pickup Station</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 pickup-field" style="display:{{ ($saved['delivery_method'] ?? '') === 'pickup' ? 'block' : 'none' }}">
                                    <label for="pickup_station_id" class="form-label">Pickup Station</label>
                                    <select name="pickup_station_id" id="pickup_station_id" class="form-select">
                                        <option value="">Select station...</option>
                                        @foreach ($pickupStations as $station)
                                            <option value="{{ $station->id }}" {{ ($saved['pickup_station_id'] ?? '') == $station->id ? 'selected' : '' }}>
                                                {{ $station->name }} - {{ $station->address }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12 delivery-field" style="display:{{ ($saved['delivery_method'] ?? 'delivery') === 'delivery' ? 'block' : 'none' }}">
                                    <label for="delivery_address" class="form-label">Delivery Address</label>
                                    <textarea name="delivery_address" id="delivery_address" class="form-control" rows="2" maxlength="500">{{ $saved['delivery_address'] ?? '' }}</textarea>
                                </div>
                                <div class="col-12">
                                    <label for="customer_notes" class="form-label">Notes (optional)</label>
                                    <textarea name="customer_notes" id="customer_notes" class="form-control" rows="2" maxlength="1000">{{ $saved['customer_notes'] ?? '' }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Step 2: Design --}}
                <div class="form-step d-none" data-step="1">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header"><h5 class="mb-0">Design Selection</h5></div>
                        <div class="card-body">
                            @if ($baseProduct)
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-1"></i>
                                    You're customizing: <strong>{{ $baseProduct->name }}</strong>
                                    <input type="hidden" name="base_product_id" value="{{ $baseProduct->id }}">
                                </div>
                            @endif

                            <div class="mb-3">
                                <label class="form-label fw-bold">How would you like to design your frock?</label>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="card border-primary h-100 design-option {{ ($saved['design_type'] ?? 'existing') === 'existing' ? 'border-2' : '' }}" data-type="existing">
                                        <div class="card-body text-center">
                                            <i class="bi bi-palette display-4 text-primary"></i>
                                            <h5 class="mt-3">Customize an Existing Design</h5>
                                            <p class="text-muted small">Select one of our frock designs and customize it with your preferences.</p>
                                            <input type="radio" name="design_type" value="existing" class="d-none" {{ ($saved['design_type'] ?? 'existing') === 'existing' ? 'checked' : '' }}>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card h-100 design-option {{ ($saved['design_type'] ?? '') === 'reference' ? 'border-primary border-2' : '' }}" data-type="reference">
                                        <div class="card-body text-center">
                                            <i class="bi bi-cloud-upload display-4 text-primary"></i>
                                            <h5 class="mt-3">Upload a Reference Design</h5>
                                            <p class="text-muted small">Upload a picture of the frock you want us to create.</p>
                                            <input type="radio" name="design_type" value="reference" class="d-none" {{ ($saved['design_type'] ?? '') === 'reference' ? 'checked' : '' }}>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div id="referenceUpload" class="mt-3" style="display:{{ ($saved['design_type'] ?? '') === 'reference' ? 'block' : 'none' }}">
                                <label class="form-label">Upload Reference Images</label>
                                <input type="file" name="reference_files[]" class="form-control" multiple accept="image/jpeg,image/png,image/webp,application/pdf">
                                <small class="text-muted">Max 5 files. JPG, PNG, WebP, PDF. Max {{ $fileService->getMaxFileSizeMb() ?? 10 }}MB each.</small>
                                <div class="alert alert-warning mt-2 small">
                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                    Reference images are used as design inspiration. The final garment may differ slightly due to fabric availability, construction methods and other production considerations.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Step 3: Customize --}}
                <div class="form-step d-none" data-step="2">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header"><h5 class="mb-0">Customization Options</h5></div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Dress Style</label>
                                    <select name="dress_style" class="form-select">
                                        <option value="">Select style...</option>
                                        @foreach ($styles as $style)
                                            <option value="{{ $style->name }}" {{ ($saved['dress_style'] ?? '') === $style->name ? 'selected' : '' }}>{{ $style->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Sleeve Style</label>
                                    <select name="sleeve" class="form-select">
                                        <option value="">Select sleeve...</option>
                                        @foreach ($sleeves as $sleeve)
                                            <option value="{{ $sleeve->name }}" {{ ($saved['sleeve'] ?? '') === $sleeve->name ? 'selected' : '' }}>{{ $sleeve->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Neckline</label>
                                    <select name="neckline" class="form-select">
                                        <option value="">Select neckline...</option>
                                        @foreach ($necklines as $neckline)
                                            <option value="{{ $neckline->name }}" {{ ($saved['neckline'] ?? '') === $neckline->name ? 'selected' : '' }}>{{ $neckline->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Skirt Style</label>
                                    <select name="skirt" class="form-select">
                                        <option value="">Select skirt...</option>
                                        @foreach ($skirts as $skirt)
                                            <option value="{{ $skirt->name }}" {{ ($saved['skirt'] ?? '') === $skirt->name ? 'selected' : '' }}>{{ $skirt->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Length</label>
                                    <select name="length" class="form-select">
                                        <option value="">Select length...</option>
                                        @foreach ($lengths as $length)
                                            <option value="{{ $length->name }}" {{ ($saved['length'] ?? '') === $length->name ? 'selected' : '' }}>{{ $length->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Waist Style</label>
                                    <select name="waist" class="form-select">
                                        <option value="">Select waist...</option>
                                        @foreach ($waists as $waist)
                                            <option value="{{ $waist->name }}" {{ ($saved['waist'] ?? '') === $waist->name ? 'selected' : '' }}>{{ $waist->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Step 4: Fabric & Colour --}}
                <div class="form-step d-none" data-step="3">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header"><h5 class="mb-0">Fabric & Colour</h5></div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Fabric</label>
                                    <select name="fabric" class="form-select">
                                        <option value="">Select fabric...</option>
                                        @foreach ($fabrics as $fabric)
                                            <option value="{{ $fabric->name }}" {{ ($saved['fabric'] ?? '') === $fabric->name ? 'selected' : '' }}>
                                                {{ $fabric->name }} {{ $fabric->availability !== 'available' ? '(' . $fabric->availability . ')' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Primary Colour *</label>
                                    <div class="d-flex flex-wrap gap-2 mb-2">
                                        @foreach ($colours as $colour)
                                            <div class="colour-swatch {{ ($saved['primary_colour'] ?? '') === $colour->name ? 'selected' : '' }}"
                                                 style="width:32px;height:32px;border-radius:50%;background:{{ $colour->hex ?? '#ccc' }};border:2px solid {{ ($saved['primary_colour'] ?? '') === $colour->name ? '#0d6efd' : '#dee2e6' }};cursor:pointer;"
                                                 data-colour="{{ $colour->name }}" title="{{ $colour->name }}">
                                            </div>
                                        @endforeach
                                    </div>
                                    <input type="hidden" name="primary_colour" id="primary_colour" value="{{ $saved['primary_colour'] ?? '' }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Secondary Colour (optional)</label>
                                    <input type="text" name="secondary_colour" class="form-control" placeholder="e.g. White" value="{{ $saved['secondary_colour'] ?? '' }}" maxlength="128">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Accent Colour (optional)</label>
                                    <input type="text" name="accent_colour" class="form-control" placeholder="e.g. Gold" value="{{ $saved['accent_colour'] ?? '' }}" maxlength="128">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Custom Colour Description (optional)</label>
                                    <input type="text" name="custom_colour_description" class="form-control" placeholder="Describe your preferred colour..." value="{{ $saved['custom_colour_description'] ?? '' }}" maxlength="500">
                                    <small class="text-muted">Or upload a colour reference image below.</small>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Colour Reference Images (optional)</label>
                                    <input type="file" name="colour_files[]" class="form-control" multiple accept="image/jpeg,image/png,image/webp">
                                    <small class="text-muted">Upload images showing your desired colour.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Step 5: Measurements --}}
                <div class="form-step d-none" data-step="4">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header"><h5 class="mb-0">Measurements</h5></div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Measurement Type</label>
                                <div class="d-flex gap-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="measurement_type" id="measure_standard" value="standard" checked>
                                        <label class="form-check-label" for="measure_standard">Standard Size (Age-based)</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="measurement_type" id="measure_custom" value="custom">
                                        <label class="form-check-label" for="measure_custom">Custom Measurements</label>
                                    </div>
                                </div>
                            </div>

                            <div id="standardSizeSection">
                                <label class="form-label">Select Standard Size</label>
                                <select name="standard_size" class="form-select w-auto">
                                    <option value="">Select age...</option>
                                    @foreach (\App\Models\AgeRange::where('is_active', true)->orderBy('name')->get() as $age)
                                        <option value="{{ $age->name }}" {{ ($saved['standard_size'] ?? '') === $age->name ? 'selected' : '' }}>{{ $age->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div id="customMeasurementsSection" class="d-none">
                                <div class="alert alert-info small">
                                    <i class="bi bi-info-circle me-1"></i>
                                    <strong>How to Measure:</strong> Use a soft measuring tape. Keep it comfortably snug but not tight.
                                    <a href="#" data-bs-toggle="modal" data-bs-target="#measurementGuideModal">View measurement guide</a>
                                </div>

                                <div id="measurementRows">
                                    @foreach ($measurementFields as $field)
                                        <div class="row g-2 mb-2 align-items-center measurement-row">
                                            <div class="col-md-4">
                                                <label class="form-label small">{{ $field->label }}</label>
                                            </div>
                                            <div class="col-md-4">
                                                <input type="number" name="measurements[{{ $loop->index }}][value]" class="form-control form-control-sm" step="0.1" min="0" placeholder="Value">
                                                <input type="hidden" name="measurements[{{ $loop->index }}][type]" value="{{ $field->name }}">
                                            </div>
                                            <div class="col-md-2">
                                                <select name="measurements[{{ $loop->index }}][unit]" class="form-select form-select-sm">
                                                    <option value="cm">cm</option>
                                                    <option value="in">inches</option>
                                                </select>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="form-check mt-3">
                                    <input class="form-check-input" type="checkbox" id="noMeasurements" name="no_measurements" value="1">
                                    <label class="form-check-label" for="noMeasurements">I don't have the measurements</label>
                                    <small class="text-muted d-block">Our team will contact you to help with measurements.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Step 6: Instructions --}}
                <div class="form-step d-none" data-step="5">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header"><h5 class="mb-0">Additional Instructions</h5></div>
                        <div class="card-body">
                            <label class="form-label fw-bold">Additional Customization Instructions</label>
                            <textarea name="customer_notes_extra" class="form-control" rows="5" maxlength="1000" placeholder="Please provide any additional details or special instructions for your custom frock...">{{ $saved['customer_notes_extra'] ?? '' }}</textarea>
                            <small class="text-muted">Max 1,000 characters.</small>
                        </div>
                    </div>
                </div>

                {{-- Step 7: Summary --}}
                <div class="form-step d-none" data-step="6">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header"><h5 class="mb-0">Order Summary</h5></div>
                        <div class="card-body" id="summaryContent">
                            {{-- Populated by JavaScript --}}
                        </div>
                    </div>

                    <div class="card shadow-sm mb-4 border-warning">
                        <div class="card-body">
                            <h6 class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i> Return Policy for Custom Orders</h6>
                            <p class="small mb-2">Custom frocks are made to order and <strong>cannot be returned</strong> for change of mind or incorrect measurements provided by the customer. Returns are accepted for manufacturing defects or errors made by KidsFlairr.</p>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="return_policy_acknowledged" id="return_policy_acknowledged" value="1" required>
                                <label class="form-check-label" for="return_policy_acknowledged">
                                    I confirm that the information and measurements provided are correct, and I accept the <a href="{{ route('shop.return-policy') }}" target="_blank">return policy</a> for custom orders.
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Navigation buttons --}}
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

{{-- Measurement Guide Modal --}}
<div class="modal fade" id="measurementGuideModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">How to Measure</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                @php $guides = \App\Models\CustomOrderMeasurementGuide::active()->get(); @endphp
                @forelse ($guides as $guide)
                    <div class="mb-3">
                        <h6 class="fw-bold">{{ $guide->name }}</h6>
                        <p class="mb-1">{{ $guide->description }}</p>
                        @if ($guide->illustration_path)
                            <img src="{{ Storage::url($guide->illustration_path) }}" alt="{{ $guide->name }}" class="img-fluid mt-2" style="max-height:150px;">
                        @endif
                    </div>
                    @if (!$loop->last)<hr>@endif
                @empty
                    <p class="text-muted">Measurement guide not available yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let currentStep = 0;
    const totalSteps = 7;
    const form = document.getElementById('customFrockForm');
    const steps = document.querySelectorAll('.form-step');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const submitBtn = document.getElementById('submitBtn');

    function showStep(n) {
        steps.forEach(s => s.classList.add('d-none'));
        steps[n].classList.remove('d-none');

        document.querySelectorAll('.step-item').forEach((s, i) => {
            const circle = s.querySelector('.step-circle');
            const label = s.querySelector('.step-label');
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
        if (n === totalSteps - 1) {
            nextBtn.classList.add('d-none');
            submitBtn.classList.remove('d-none');
            buildSummary();
        } else {
            nextBtn.classList.remove('d-none');
            submitBtn.classList.add('d-none');
        }
    }

    nextBtn.addEventListener('click', function() {
        if (currentStep < totalSteps - 1) { currentStep++; showStep(currentStep); window.scrollTo(0, 0); }
    });

    prevBtn.addEventListener('click', function() {
        if (currentStep > 0) { currentStep--; showStep(currentStep); window.scrollTo(0, 0); }
    });

    // Delivery method toggle
    document.querySelectorAll('input[name="delivery_method"]').forEach(r => {
        r.addEventListener('change', function() {
            document.querySelector('.pickup-field').style.display = this.value === 'pickup' ? 'block' : 'none';
            document.querySelector('.delivery-field').style.display = this.value === 'delivery' ? 'block' : 'none';
        });
    });

    // Design type toggle
    document.querySelectorAll('.design-option').forEach(card => {
        card.addEventListener('click', function() {
            document.querySelectorAll('.design-option').forEach(c => c.classList.remove('border-primary', 'border-2'));
            this.classList.add('border-primary', 'border-2');
            this.querySelector('input[type="radio"]').checked = true;
            document.getElementById('referenceUpload').style.display = this.dataset.type === 'reference' ? 'block' : 'none';
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

    // Measurement type toggle
    document.querySelectorAll('input[name="measurement_type"]').forEach(r => {
        r.addEventListener('change', function() {
            document.getElementById('standardSizeSection').classList.toggle('d-none', this.value !== 'standard');
            document.getElementById('customMeasurementsSection').classList.toggle('d-none', this.value !== 'custom');
        });
    });

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
            "Age": fd.get('child_age') ? fd.get('child_age') + ' years' : '—',
            "Design Type": fd.get('design_type') === 'existing' ? 'Existing Design' : 'Custom Reference',
            "Dress Style": fd.get('dress_style') || '—',
            "Sleeve": fd.get('sleeve') || '—',
            "Neckline": fd.get('neckline') || '—',
            "Skirt": fd.get('skirt') || '—',
            "Length": fd.get('length') || '—',
            "Waist": fd.get('waist') || '—',
            "Fabric": fd.get('fabric') || '—',
            "Primary Colour": fd.get('primary_colour') || '—',
            "Secondary Colour": fd.get('secondary_colour') || '—',
            "Accent Colour": fd.get('accent_colour') || '—',
            "Delivery": fd.get('delivery_method') === 'pickup' ? 'Pickup Station' : 'Home Delivery',
        };
        for (const [k, v] of Object.entries(fields)) {
            html += `<tr><td class="text-muted">${escapeHtml(k)}</td><td class="fw-bold">${escapeHtml(v)}</td></tr>`;
        }
        if (fd.get('measurement_type') === 'custom') {
            document.querySelectorAll('.measurement-row').forEach(row => {
                const type = row.querySelector('input[type="hidden"]')?.value;
                const val = row.querySelector('input[type="number"]')?.value;
                const unit = row.querySelector('select')?.value || 'cm';
                if (val) html += `<tr><td class="text-muted">${escapeHtml(type)}</td><td>${escapeHtml(val)} ${escapeHtml(unit)}</td></tr>`;
            });
        } else {
            html += `<tr><td class="text-muted">Size</td><td>${escapeHtml(fd.get('standard_size') || 'Standard')}</td></tr>`;
        }
        if (fd.get('customer_notes')) {
            html += `<tr><td class="text-muted">Notes</td><td>${escapeHtml(fd.get('customer_notes'))}</td></tr>`;
        }
        html += '</table>';
        document.getElementById('summaryContent').innerHTML = html;
    }

    showStep(0);
});
</script>
@endpush
