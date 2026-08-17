@extends('layouts.shop')

@section('title', 'Start Custom Order')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <h1 class="h3 mb-2">Create a Custom Frock</h1>
            <p class="text-muted mb-4">Create a frock designed specially for your little one. Choose your style, fabric, colour, measurements and other details.</p>

            {{-- Step indicator (populated by JS based on design type) --}}
            <div class="d-flex justify-content-between mb-4" id="stepIndicator"></div>

            <form id="customFrockForm" method="POST" action="{{ route('shop.custom-frock.store') }}" enctype="multipart/form-data">
                @csrf

                <input type="hidden" name="base_product_id" value="{{ $baseProduct?->id }}">

                {{-- Step 1: Basic Info --}}
                <div class="form-step" data-step="0" data-design="both" data-label="Basic Info">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header"><h5 class="mb-0">Basic Information</h5></div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="child_name" class="form-label">Child's Name / Nickname <span class="text-danger">*</span></label>
                                    <input type="text" name="child_name" id="child_name" class="form-control" value="{{ $saved['child_name'] ?? '' }}" maxlength="100" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="child_age" class="form-label">Age (years) <span class="text-danger">*</span></label>
                                    <input type="number" name="child_age" id="child_age" class="form-control" value="{{ $saved['child_age'] ?? '' }}" min="0" max="18" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="child_gender" class="form-label">Gender <span class="text-danger">*</span></label>
                                    <select name="child_gender" id="child_gender" class="form-select" required>
                                        <option value="">Select...</option>
                                        <option value="girl" {{ ($saved['child_gender'] ?? '') === 'girl' ? 'selected' : '' }}>Girl</option>
                                        <option value="boy" {{ ($saved['child_gender'] ?? '') === 'boy' ? 'selected' : '' }}>Boy</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="delivery_method" class="form-label">Delivery Method <span class="text-danger">*</span></label>
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
                                    <label for="delivery_address" class="form-label">Delivery Address <span class="text-danger">*</span></label>
                                    <textarea name="delivery_address" id="delivery_address" class="form-control" rows="2" maxlength="500" {{ ($saved['delivery_method'] ?? 'delivery') === 'delivery' ? 'required' : '' }}>{{ $saved['delivery_address'] ?? '' }}</textarea>
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
                <div class="form-step d-none" data-step="1" data-design="both" data-label="Design">
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
                                <label class="form-label fw-bold">How would you like to design your frock? <span class="text-danger">*</span></label>
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
                <div class="form-step d-none" data-step="2" data-design="customize" data-label="Customize">
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
                <div class="form-step d-none" data-step="3" data-design="customize" data-label="Fabric & Colour">
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
                                    <label class="form-label fw-bold">Primary Colour <span class="text-danger">*</span></label>
                                    @if($colours->count())
                                    <div class="d-flex flex-wrap gap-2 mb-2">
                                        @foreach ($colours as $colour)
                                            <div class="colour-swatch {{ ($saved['primary_colour'] ?? '') === $colour->name ? 'selected' : '' }}"
                                                 style="width:32px;height:32px;border-radius:50%;background:{{ $colour->hex ?? '#ccc' }};border:2px solid {{ ($saved['primary_colour'] ?? '') === $colour->name ? '#0d6efd' : '#dee2e6' }};cursor:pointer;"
                                                 data-colour="{{ $colour->name }}" title="{{ $colour->name }}">
                                            </div>
                                        @endforeach
                                    </div>
                                    @endif
                                    <input type="text" name="primary_colour" id="primary_colour" class="form-control form-control-sm" value="{{ $saved['primary_colour'] ?? '' }}" placeholder="e.g. Pink, Red, Blue" maxlength="128" required>
                                    <small class="text-muted">Pick a swatch above or type a colour name.</small>
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
                <div class="form-step d-none" data-step="4" data-design="both" data-label="Measurements">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header"><h5 class="mb-0">Size & Measurements</h5></div>
                        <div class="card-body">
                            {{-- Upload-only fields --}}
                            <div id="uploadSizeFields" class="d-none mb-3">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Age Range <span class="text-danger">*</span></label>
                                        <select name="standard_size" class="form-select" id="upload_age_range">
                                            <option value="">Select age...</option>
                                            @foreach (\App\Models\AgeRange::where('is_active', true)->orderBy('name')->get() as $age)
                                                <option value="{{ $age->name }}" {{ ($saved['standard_size'] ?? '') === $age->name ? 'selected' : '' }}>{{ $age->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Size Preference</label>
                                        <input type="text" name="child_size" class="form-control" placeholder="e.g. Small, Medium, Large" value="{{ $saved['child_size'] ?? '' }}">
                                        <small class="text-muted">Optional — our team will confirm during review.</small>
                                    </div>
                                </div>
                            </div>

                            {{-- Customize-only: measurement type toggle --}}
                            <div id="customizeMeasurementFields">
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
                                    <label class="form-label">Select Standard Size <span class="text-danger">*</span></label>
                                    <select name="standard_size" class="form-select w-auto" id="standard_size_select">
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
                </div>

                {{-- Step 6: Instructions --}}
                <div class="form-step d-none" data-step="5" data-design="customize" data-label="Instructions">
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
                <div class="form-step d-none" data-step="6" data-design="both" data-label="Summary">
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
    const form = document.getElementById('customFrockForm');
    const allSteps = Array.from(document.querySelectorAll('.form-step'));
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const submitBtn = document.getElementById('submitBtn');
    const indicator = document.getElementById('stepIndicator');

    let activeSteps = [];
    let currentStep = 0;
    let designType = '{{ $saved["design_type"] ?? "existing" }}';

    // ── Build step indicator ────────────────────────────────────────────────
    function buildIndicator() {
        indicator.innerHTML = '';
        activeSteps.forEach((step, i) => {
            const div = document.createElement('div');
            div.className = 'step-item text-center flex-fill';
            div.dataset.stepIndex = i;
            div.innerHTML = `
                <div class="step-circle mx-auto mb-1 ${i === 0 ? 'bg-primary text-white' : 'bg-light text-muted'}">${i + 1}</div>
                <small class="step-label d-none d-md-inline ${i === 0 ? 'text-primary fw-bold' : 'text-muted'}">${step.dataset.label}</small>`;
            indicator.appendChild(div);
        });
    }

    // ── Filter steps based on design type ──────────────────────────────────
    function filterSteps() {
        activeSteps = allSteps.filter(step => {
            const d = step.dataset.design;
            return d === 'both' || d === designType;
        });
        buildIndicator();

        // Show/hide upload vs customize measurement fields
        const uploadFields = document.getElementById('uploadSizeFields');
        const customizeFields = document.getElementById('customizeMeasurementFields');
        if (uploadFields) uploadFields.classList.toggle('d-none', designType !== 'reference');
        if (customizeFields) customizeFields.classList.toggle('d-none', designType !== 'existing');
    }

    // ── Show a specific step ───────────────────────────────────────────────
    function showStep(n) {
        allSteps.forEach(s => s.classList.add('d-none'));
        if (activeSteps[n]) activeSteps[n].classList.remove('d-none');

        document.querySelectorAll('.step-item').forEach((item, i) => {
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

        const isLast = n === activeSteps.length - 1;
        prevBtn.style.display = n > 0 ? 'inline-block' : 'none';
        nextBtn.classList.toggle('d-none', isLast);
        submitBtn.classList.toggle('d-none', !isLast);
        if (isLast) buildSummary();
    }

    // ── Navigation ─────────────────────────────────────────────────────────
    nextBtn.addEventListener('click', function() {
        if (!validateStep(currentStep)) return;
        if (currentStep < activeSteps.length - 1) { currentStep++; showStep(currentStep); window.scrollTo(0, 0); }
    });

    prevBtn.addEventListener('click', function() {
        if (currentStep > 0) { currentStep--; showStep(currentStep); window.scrollTo(0, 0); }
    });

    // ── Validation ─────────────────────────────────────────────────────────
    function validateStep(step) {
        const el = activeSteps[step];
        if (!el) return true;
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

        // Design step: radio must be selected
        if (el.dataset.step === '1') {
            const checked = el.querySelector('input[name="design_type"]:checked');
            if (!checked) valid = false;
        }

        // Measurements step
        if (el.dataset.step === '4') {
            if (designType === 'reference') {
                const ageRange = el.querySelector('#upload_age_range');
                if (ageRange && !ageRange.value) { ageRange.classList.add('is-invalid'); valid = false; }
            } else {
                const type = el.querySelector('input[name="measurement_type"]:checked')?.value;
                if (type === 'standard') {
                    const ss = el.querySelector('#standard_size_select');
                    if (ss && !ss.value) { ss.classList.add('is-invalid'); valid = false; }
                }
                if (type === 'custom') {
                    const noMeas = el.querySelector('#noMeasurements');
                    if (!noMeas?.checked) {
                        el.querySelectorAll('.measurement-row input[type="number"]').forEach(inp => {
                            if (!inp.value) { inp.classList.add('is-invalid'); valid = false; }
                        });
                    }
                }
            }
        }

        if (!valid) {
            const first = el.querySelector('.is-invalid');
            if (first) first.focus();
        }
        return valid;
    }

    // ── Delivery method toggle ─────────────────────────────────────────────
    document.querySelectorAll('input[name="delivery_method"]').forEach(r => {
        r.addEventListener('change', function() {
            document.querySelector('.pickup-field').style.display = this.value === 'pickup' ? 'block' : 'none';
            document.querySelector('.delivery-field').style.display = this.value === 'delivery' ? 'block' : 'none';
        });
    });

    // ── Design type toggle ─────────────────────────────────────────────────
    document.querySelectorAll('.design-option').forEach(card => {
        card.addEventListener('click', function() {
            document.querySelectorAll('.design-option').forEach(c => c.classList.remove('border-primary', 'border-2'));
            this.classList.add('border-primary', 'border-2');
            this.querySelector('input[type="radio"]').checked = true;
            document.getElementById('referenceUpload').style.display = this.dataset.type === 'reference' ? 'block' : 'none';
            designType = this.dataset.type === 'reference' ? 'reference' : 'existing';
            currentStep = 0;
            filterSteps();
            showStep(0);
        });
    });

    // ── Colour swatches ────────────────────────────────────────────────────
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

    // ── Measurement type toggle ────────────────────────────────────────────
    document.querySelectorAll('input[name="measurement_type"]').forEach(r => {
        r.addEventListener('change', function() {
            document.getElementById('standardSizeSection').classList.toggle('d-none', this.value !== 'standard');
            document.getElementById('customMeasurementsSection').classList.toggle('d-none', this.value !== 'custom');
        });
    });

    // ── Helpers ────────────────────────────────────────────────────────────
    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }

    // ── Build summary ──────────────────────────────────────────────────────
    function buildSummary() {
        const fd = new FormData(form);
        let html = '<h6 class="fw-bold text-primary mb-3">CUSTOM FROCK REQUEST</h6>';
        html += '<table class="table table-sm">';
        const fields = {
            "Child's Name": fd.get('child_name') || '—',
            "Age": fd.get('child_age') ? fd.get('child_age') + ' years' : '—',
            "Design Type": fd.get('design_type') === 'existing' ? 'Customize Existing Design' : 'Upload Reference Design',
            "Delivery": fd.get('delivery_method') === 'pickup' ? 'Pickup Station' : 'Home Delivery',
        };

        if (designType === 'existing') {
            Object.assign(fields, {
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
            });
        }

        // Size / measurements
        if (designType === 'reference') {
            fields['Age Range'] = fd.get('standard_size') || '—';
            fields['Size Preference'] = fd.get('child_size') || 'Not specified';
        } else {
            if (fd.get('measurement_type') === 'custom') {
                document.querySelectorAll('.measurement-row').forEach(row => {
                    const type = row.querySelector('input[type="hidden"]')?.value;
                    const val = row.querySelector('input[type="number"]')?.value;
                    const unit = row.querySelector('select')?.value || 'cm';
                    if (val) fields[type] = val + ' ' + unit;
                });
            } else {
                fields['Size'] = fd.get('standard_size') || 'Standard';
            }
        }

        if (fd.get('customer_notes')) fields['Notes'] = fd.get('customer_notes');

        for (const [k, v] of Object.entries(fields)) {
            html += `<tr><td class="text-muted">${escapeHtml(k)}</td><td class="fw-bold">${escapeHtml(v)}</td></tr>`;
        }
        html += '</table>';
        document.getElementById('summaryContent').innerHTML = html;
    }

    // ── Boot ───────────────────────────────────────────────────────────────
    filterSteps();
    showStep(0);
});
</script>
@endpush
