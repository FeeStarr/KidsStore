@extends('layouts.shop')

@section('title', 'Custom Frock Request')

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
                <input type="hidden" name="submission_token" id="submissionToken" value="{{ $submissionToken }}">
                {{-- The design this request was started from. The measurement requirement is
                     read from the design on the server, never from the browser. --}}
                @if($creation)
                    <input type="hidden" name="creation_id" value="{{ $creation->id }}">
                @endif

                {{-- Step 1: Basic Info --}}
                <div class="form-step" data-step="0">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header"><h5 class="mb-0">Basic Information</h5></div>
                        <div class="card-body">
                            @if($creation)
                                {{-- The design this request was started from. Saying so keeps the
                                     customer on the design they chose, and states plainly that a
                                     design cut closely will need their measurements confirmed. --}}
                                <div class="alert alert-info d-flex gap-3 align-items-start">
                                    @if($creation->image_path)
                                        <img src="{{ $creation->image_url }}" alt="{{ $creation->title }}"
                                             class="rounded flex-shrink-0" style="width:64px;height:64px;object-fit:cover">
                                    @endif
                                    <div>
                                        <div class="fw-bold">Requesting: {{ $creation->title }}</div>
                                        @if($creation->age_range)
                                            <div class="small">Suggested age range: {{ $creation->age_range }}</div>
                                        @endif
                                        @if($creation->requires_measurement_confirmation)
                                            <div class="small mt-1">
                                                <i class="bi bi-rulers me-1"></i>This design is cut to fit closely, so we will
                                                ask you to confirm your child's measurements before we begin.
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="child_name" class="form-label">Child's Name / Nickname <span class="text-danger">*</span></label>
                                    <input type="text" name="child_name" id="child_name" class="form-control" value="{{ old('child_name', $saved['child_name'] ?? '') }}" maxlength="100" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Gender</label>
                                    <div class="form-control-plaintext fw-bold text-muted"><i class="bi bi-gender-female text-danger me-1"></i> Girl</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="category" class="form-label">Category <span class="text-muted fw-normal">(optional)</span></label>
                                    <select name="category" id="category" class="form-select">
                                        <option value="">No category</option>
                                        @foreach (\App\Models\CustomOrder::CATEGORIES as $key => $label)
                                            <option value="{{ $key }}" {{ old('category', $saved['category'] ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="delivery_method" class="form-label">Delivery Method <span class="text-danger">*</span></label>
                                    <div class="d-flex gap-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="delivery_method" id="delivery_home" value="delivery" {{ (old('delivery_method', $saved['delivery_method'] ?? 'delivery')) === 'delivery' ? 'checked' : '' }}>
                                            <label class="form-check-label" for="delivery_home">Home Delivery</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="delivery_method" id="delivery_pickup" value="pickup" {{ (old('delivery_method', $saved['delivery_method'] ?? '')) === 'pickup' ? 'checked' : '' }}>
                                            <label class="form-check-label" for="delivery_pickup">Pickup Station</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 pickup-field" style="display:none">
                                    <label for="pickup_station_id" class="form-label">Pickup Station</label>
                                    <select name="pickup_station_id" id="pickup_station_id" class="form-select">
                                        <option value="">Select station...</option>
                                        @foreach ($pickupStations as $station)
                                            <option value="{{ $station->id }}" {{ old('pickup_station_id', $saved['pickup_station_id'] ?? '') == $station->id ? 'selected' : '' }}>{{ $station->name }} - {{ $station->address }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12 delivery-field">
                                    <label for="delivery-location-select" class="form-label">Delivery Location <span class="text-danger">*</span></label>
                                    <select name="delivery_location_id" id="delivery-location-select" class="form-select">
                                        <option value="">-- Select your location --</option>
                                        @foreach ($deliveryLocations as $loc)
                                            <option value="{{ $loc->id }}"
                                                    {{ old('delivery_location_id', $saved['delivery_location_id'] ?? '') == $loc->id ? 'selected' : '' }}>
                                                {{ $loc->name }}{{ $loc->state ? ', ' . $loc->state : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    {{-- No price shown here: delivery is charged from the quote. --}}
                                    <div id="delivery-unavailable" class="alert alert-warning py-2 mb-3 mt-2" style="display:none">
                                        <i class="bi bi-exclamation-triangle me-1"></i> No delivery agent available for this location.
                                    </div>
                                    <label for="delivery_address" class="form-label">Delivery Address <span class="text-danger">*</span></label>
                                    <textarea name="delivery_address" id="delivery_address" class="form-control" rows="2" maxlength="500">{{ old('delivery_address', $saved['delivery_address'] ?? '') }}</textarea>
                                </div>
                                <div class="col-12">
                                    <label for="customer_notes" class="form-label">Notes (optional)</label>
                                    <textarea name="customer_notes" id="customer_notes" class="form-control" rows="2" maxlength="1000" placeholder="Any special requests...">{{ old('customer_notes', $saved['customer_notes'] ?? '') }}</textarea>
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

                                {{-- Sizing. A published chart offers a real size choice; without one the
                                     age range is kept so existing traffic is never blocked. --}}
                                <div class="col-12">
                                    @if($sizeChart)
                                        @php
                                            $chartGroups = $sizeChart->fitGroups();
                                            // The design may suggest an age band, but only one the
                                            // chart actually offers. Anything else is left to the
                                            // customer rather than forced onto them.
                                            $prefilledBand = $creationAgeRange !== null
                                                && collect($chartGroups)->pluck('label')->contains($creationAgeRange)
                                                    ? $creationAgeRange
                                                    : null;
                                        @endphp

                                        <label class="form-label fw-bold">Sizing <span class="text-danger">*</span></label>
                                        <input type="hidden" name="sizing_method" value="{{ old('sizing_method', \App\Models\CustomOrder::SIZING_STANDARD) }}">
                                        <input type="hidden" name="size_chart_id" value="{{ old('size_chart_id', $sizeChart->id) }}">

                                        <select name="selected_chart_size" class="form-select" required data-frock-age>
                                            <option value="">Choose an age band...</option>
                                            @foreach($chartGroups as $group)
                                                <option value="{{ $group['label'] }}"
                                                    @selected(old('selected_chart_size', $saved['selected_chart_size'] ?? $prefilledBand ?? '') === $group['label'])>
                                                    {{ $group['label'] }}
                                                </option>
                                            @endforeach
                                        </select>

                                        <label class="form-label fw-bold mt-3">Fitting</label>
                                        <select name="selected_chart_fit" class="form-select" data-frock-fit>
                                            <option value="">Choose a fitting...</option>
                                            @foreach($chartGroups as $group)
                                                <optgroup label="{{ $group['label'] }}">
                                                    @foreach($group['options'] as $option)
                                                        @php
                                                            // The standard cut is submitted as a named
                                                            // option so it cannot be confused with
                                                            // "not chosen yet".
                                                            $value = $option['key'] === \App\Models\SizeChartEntry::FIT_UNASSIGNED
                                                                ? \App\Models\SizeChartEntry::FIT_UNASSIGNED
                                                                : $option['key'];
                                                        @endphp
                                                        <option value="{{ $value }}"
                                                            data-band="{{ $group['label'] }}"
                                                            @selected(old('selected_chart_fit', $saved['selected_chart_fit'] ?? '') === $value)>
                                                            {{ $option['label'] }}
                                                        </option>
                                                    @endforeach
                                                </optgroup>
                                            @endforeach
                                        </select>
                                        <small class="form-text text-muted">
                                            Not sure?
                                            <a href="{{ route('shop.size-guide') }}" target="_blank" rel="noopener">Check the {{ $sizeChart->name }} size guide</a>
                                            before choosing &mdash; it takes a minute and helps us get the fit right.
                                        </small>

                                        <details class="mt-2">
                                            <summary class="small text-muted" style="cursor:pointer">Show the measurements for each fitting</summary>
                                            @php
                                                $chartTypes = $sizeChart->entries
                                                    ->flatMap(fn ($e) => $e->measurements->pluck('measurement_type'))
                                                    ->unique()->sort()->values();
                                            @endphp
                                            <div class="table-responsive mt-2">
                                                <table class="table table-sm table-bordered small mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th>Size</th>
                                                            @foreach($chartTypes as $type)
                                                                <th>{{ ucwords(str_replace('_', ' ', $type)) }}</th>
                                                            @endforeach
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($chartGroups as $group)
                                                            @foreach($group['options'] as $option)
                                                                <tr data-frock-row-band="{{ $group['label'] }}">
                                                                    <th scope="row">{{ $option['entry']->combinedLabel() }}</th>
                                                                    @foreach($chartTypes as $type)
                                                                        @php $cell = $option['entry']->measurements->firstWhere('measurement_type', $type); @endphp
                                                                        <td>{{ $cell && $cell->displayValue() ? $cell->displayValue() : '—' }}</td>
                                                                    @endforeach
                                                                </tr>
                                                            @endforeach
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                            <small class="form-text text-muted">
                                                @if($sizeChart->default_basis === \App\Models\SizeChart::BASIS_BODY)
                                                    These are body measurements &mdash; measure your child.
                                                @else
                                                    These are finished garment measurements &mdash; lay a similar frock flat and measure straight across it.
                                                @endif
                                            </small>
                                        </details>
                                    @else
                                        <label class="form-label fw-bold">Age Range <span class="text-danger">*</span></label>
                                        <select name="standard_size" class="form-select" required>
                                            <option value="">Select age...</option>
                                            @foreach(\App\Models\AgeRange::where('is_active', true)->orderBy('name')->get() as $age)
                                                <option value="{{ $age->name }}" {{ old('standard_size', $saved['standard_size'] ?? $creationAgeRange ?? '') === $age->name ? 'selected' : '' }}>{{ $age->name }}</option>
                                            @endforeach
                                        </select>
                                        <small class="form-text text-muted">
                                            Our size guide is being prepared. We will confirm the size with you before we cut.
                                        </small>
                                    @endif
                                </div>

                                {{-- Optional: send actual measurements instead of picking a size --}}
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="send_measurements"
                                               data-bs-toggle="collapse" data-bs-target="#measurementFields"
                                               aria-expanded="false" aria-controls="measurementFields">
                                        <label class="form-check-label small" for="send_measurements">
                                            I'd rather send my child's measurements
                                        </label>
                                    </div>

                                    <div class="collapse mt-2" id="measurementFields">
                                        <div class="border rounded p-3 bg-light">
                                            <p class="small text-muted mb-3">
                                                Only worth doing if your child falls between two sizes, or the style is a
                                                particularly fitted one. If your child is close to a size on the guide,
                                                choosing that size is perfectly fine.
                                            </p>

                                            @foreach($measurementFields as $field)
                                                <div class="row g-2 align-items-center mb-2">
                                                    <div class="col-sm-5">
                                                        <label class="form-label small mb-0" for="m_{{ $field->name }}">{{ $field->label }}</label>
                                                    </div>
                                                    <div class="col-sm-4">
                                                        <input type="number" step="0.1" min="1" max="500" id="m_{{ $field->name }}"
                                                               class="form-control form-control-sm"
                                                               name="measurements[{{ $field->name }}][value]"
                                                               value="{{ old('measurements.'.$field->name.'.value') }}">
                                                    </div>
                                                    <div class="col-sm-3">
                                                        <input type="text" class="form-control form-control-sm" maxlength="8"
                                                               name="measurements[{{ $field->name }}][unit]" value="cm">
                                                    </div>
                                                </div>
                                                @if(isset($bodyGuides[$field->name]))
                                                    <small class="text-muted d-block mb-2" style="margin-top:-4px">
                                                        {{ $bodyGuides[$field->name]->description }}
                                                    </small>
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                </div>

                                {{-- Primary colour --}}
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Primary Colour <span class="text-danger">*</span></label>
                                    <input type="text" name="primary_colour" id="primary_colour" class="form-control form-control-sm" value="{{ old('primary_colour', $saved['primary_colour'] ?? '') }}" placeholder="e.g. Pink, Red, Blue" maxlength="128" required>
                                    <small class="text-muted">Type the colour you would like.</small>
                                </div>

                                {{-- Secondary colour --}}
                                <div class="col-md-3">
                                    <label class="form-label fw-bold">Secondary Colour</label>
                                    <input type="text" name="secondary_colour" class="form-control" placeholder="e.g. White" value="{{ old('secondary_colour', $saved['secondary_colour'] ?? '') }}" maxlength="128">
                                </div>

                                {{-- Accent colour --}}
                                <div class="col-md-3">
                                    <label class="form-label fw-bold">Accent Colour</label>
                                    <input type="text" name="accent_colour" class="form-control" placeholder="e.g. Gold" value="{{ old('accent_colour', $saved['accent_colour'] ?? '') }}" maxlength="128">
                                </div>

                                {{-- Colour reference images --}}
                                <div class="col-12">
                                    <label class="form-label">Colour Reference Images (optional)</label>
                                    <input type="file" name="colour_files[]" class="form-control" multiple accept="image/jpeg,image/png,image/webp">
                                    <small class="text-muted">Upload images showing the colours you have in mind.</small>
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
                            <h6 class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i> Return Policy for Custom Frocks</h6>
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

@push('styles')
<style>
    /* The rows for the other age bands are hidden once an age is chosen. They stay
       in the page, so without scripting the table still shows every size. */
    .frock-row-hidden { display: none !important; }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Narrow the fitting options to the ones actually offered for the chosen age
    // band. Every option stays in the form, so without scripting the customer can
    // still see what is on offer; the server then refuses any combination the
    // chart does not actually contain.
    const ageSelect = document.querySelector('[data-frock-age]');
    const fitSelect = document.querySelector('[data-frock-fit]');
    const measureRows = Array.prototype.slice.call(document.querySelectorAll('[data-frock-row-band]'));

    if (ageSelect && fitSelect) {
        const fitOptions = Array.prototype.slice.call(fitSelect.querySelectorAll('option[data-band]'));

        function narrowFittings() {
            const band = ageSelect.value;

            fitOptions.forEach(function (option) {
                const offered = !band || option.getAttribute('data-band') === band;
                option.disabled = !offered;
                option.hidden = !offered;
            });

            const current = fitSelect.options[fitSelect.selectedIndex];
            if (current && current.disabled) {
                fitSelect.value = '';
            }
        }

        ageSelect.addEventListener('change', narrowFittings);
        narrowFittings();
    }

    // The measurement table shows the sizes for the age band being chosen, not
    // every size in the chart. The rows for the other bands stay in the page, so
    // without scripting the table still shows everything.
    function narrowMeasurements() {
        if (! ageSelect) return;

        const band = ageSelect.value;

        measureRows.forEach(function (row) {
            const sameBand = !band || row.getAttribute('data-frock-row-band') === band;
            row.classList.toggle('frock-row-hidden', !sameBand);
        });
    }

    if (ageSelect) {
        ageSelect.addEventListener('change', narrowMeasurements);
        narrowMeasurements();
    }

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
        updateNextState();
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

    function stepIsValid(step) {
        const el = steps[step];
        let valid = true;

        el.querySelectorAll('[required]').forEach(f => {
            if (f.offsetParent === null) return;
            if (!f.value || (f.type === 'checkbox' && !f.checked)) {
                valid = false;
            }
        });

        return valid;
    }

    function updateNextState() {
        nextBtn.disabled = !stepIsValid(currentStep);
    }

    form.addEventListener('input', updateNextState);
    form.addEventListener('change', updateNextState);

    // Block duplicate submissions. The lock is applied on 'submit', not 'click', so a
    // failed HTML5 validation leaves the button usable: this form has required fields on
    // earlier steps which are hidden with `d-none`, and Chrome refuses to submit (never
    // firing 'submit') when such a field is empty or unfocusable. Locking on click
    // disabled the button before that could happen, leaving it stuck on "Submitting...".
    // The server-side submission_token remains the real guarantee.
    let isSubmitting = false;

    function lockSubmitBtn() {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Submitting...';
    }

    form.addEventListener('submit', function(e) {
        if (isSubmitting) { e.preventDefault(); return; }
        isSubmitting = true;
        lockSubmitBtn();
    });

    // Delivery method toggle - set initial required state
    const deliveryRadio = document.getElementById('delivery_home');
    if (deliveryRadio && deliveryRadio.checked) {
        document.getElementById('delivery_address').required = true;
        document.getElementById('delivery-location-select').required = true;
    }

    document.querySelectorAll('input[name="delivery_method"]').forEach(r => {
        r.addEventListener('change', function() {
            const isPickup = this.value === 'pickup';
            document.querySelector('.pickup-field').style.display = isPickup ? 'block' : 'none';
            document.querySelector('.delivery-field').style.display = isPickup ? 'none' : 'block';
            document.getElementById('delivery_address').required = !isPickup;
            document.getElementById('delivery-location-select').required = !isPickup;
            document.getElementById('pickup_station_id').required = isPickup;
            updateDeliveryAvailability();
        });
    });

    // Delivery availability only. The price is intentionally absent: it lives on the
    // quote, and showing a figure here would set an expectation the quote may not meet.
    const locationsWithAgent = @json($deliveryCharges);
    const locationSelect = document.getElementById('delivery-location-select');
    const deliveryUnavailable = document.getElementById('delivery-unavailable');

    function updateDeliveryAvailability() {
        const locId = parseInt(locationSelect.value);
        const served = locationsWithAgent.includes(locId);

        deliveryUnavailable.style.display = locId && !served ? '' : 'none';
    }

    locationSelect.addEventListener('change', updateDeliveryAvailability);
    updateDeliveryAvailability();

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
            "Child's Name": fd.get('child_name') || '-',
            "Gender": 'Girl',
            "Design Type": 'Upload Reference',
            "Size": fd.get('selected_chart_size') || fd.get('standard_size') || '-',
            "Primary Colour": fd.get('primary_colour') || '-',
            "Secondary Colour": fd.get('secondary_colour') || '-',
            "Accent Colour": fd.get('accent_colour') || '-',
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
