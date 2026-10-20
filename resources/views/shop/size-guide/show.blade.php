@extends('layouts.shop', ['title' => 'Size Guide & How to Measure'])
@section('content')
<div class="container py-4">

    <h1 class="h3 mb-2">Size Guide &amp; How to Measure</h1>
    <p class="text-muted mb-4">
        A little check goes a long way. Measuring before you order helps us make a frock that fits comfortably first time.
    </p>

    @if($charts->isEmpty())
        <div class="alert alert-info">
            Our size guide is being prepared and will be available here shortly.
        </div>
    @else

        {{-- Only the bases genuinely present are explained, so the guide never implies
             a dataset the tailor has not supplied. --}}
        <div class="card mb-4">
            <div class="card-header">How to read this guide</div>
            <div class="card-body">
                @foreach($bases as $basis)
                    <p class="{{ $loop->last ? 'mb-0' : 'mb-2 pb-2 border-bottom' }}">
                        @if($basis === \App\Models\SizeChart::BASIS_BODY)
                            <strong>Body measurements</strong> &mdash; measure your child. Keep the tape snug but not
                            tight, and measure over light clothing.
                        @else
                            <strong>Finished garment measurements</strong> &mdash; these describe the frock itself, not
                            your child. Lay a similar frock flat, smooth it without stretching, and measure straight across.
                        @endif
                    </p>
                @endforeach
            </div>
        </div>

        @foreach($charts as $chart)
            @foreach($bases as $basis)
                @php
                    // Which bases this chart actually holds figures for.
                    $types = $chart->entries
                        ->flatMap(fn ($e) => $e->measurements->where('basis', $basis)->pluck('measurement_type'))
                        ->unique()->sort()->values();

                    // Age bands, and the fittings actually offered for each one.
                    $bands = [];
                    foreach ($chart->fitGroups() as $group) {
                        $options = [];
                        foreach ($group['options'] as $option) {
                            $options[] = [
                                'key' => $option['key'],
                                'label' => $option['label'],
                                'tiered' => $option['tiered'],
                                'entry' => $option['entry'],
                                // A fitting with no figures for this basis exists, but it
                                // cannot be chosen, and must not look choosable.
                                'available' => $option['entry']->hasBasis($basis),
                            ];
                        }
                        $bands[] = [
                            'label' => $group['label'],
                            'options' => $options,
                            'anyAvailable' => collect($options)->contains(fn ($o) => $o['available']),
                        ];
                    }
                @endphp

                @continue($types->isEmpty())

                <div class="card mb-4" data-size-guide>
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>
                            {{ $chart->name }}
                            <span class="badge text-bg-light text-dark ms-2">
                                {{ $basis === \App\Models\SizeChart::BASIS_BODY ? 'Body' : 'Finished garment' }}
                            </span>
                        </span>
                        <small class="text-muted">in centimetres</small>
                    </div>
                    <div class="card-body">

                        {{-- Choose an age band. Every fitting offered for the chosen
                             band is then shown together, so the customer can compare
                             them rather than being narrowed to one. --}}
                        <div class="mb-3 d-none" data-sg-age-step>
                            <div class="small fw-semibold mb-2">Choose an age band</div>
                            <div class="d-flex flex-wrap gap-2" role="group" aria-label="Choose an age band">
                                @foreach($bands as $bandIndex => $band)
                                    <button type="button"
                                            class="btn btn-sm btn-outline-secondary"
                                            data-sg-age-btn="{{ $bandIndex }}"
                                            aria-pressed="false"
                                            @disabled(! $band['anyAvailable'])>
                                        {{ $band['label'] }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th style="min-width:190px">Size</th>
                                        @foreach($types as $type)
                                            <th class="small">{{ ucwords(str_replace('_', ' ', $type)) }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($bands as $bandIndex => $band)
                                        @foreach($band['options'] as $option)
                                            <tr data-sg-row-band="{{ $bandIndex }}" data-sg-row-fit="{{ $option['key'] }}">
                                                <th scope="row">
                                                    {{ $option['entry']->combinedLabel() }}
                                                    @unless($option['tiered'])
                                                        <span class="badge text-bg-light text-dark ms-1">
                                                            {{ \App\Models\SizeChartEntry::FIT_UNASSIGNED_LABEL }}
                                                        </span>
                                                    @endunless
                                                    @unless($option['available'])
                                                        <span class="d-block small fw-normal text-muted">
                                                            No {{ $basis === \App\Models\SizeChart::BASIS_BODY ? 'body' : 'garment' }} figures yet
                                                        </span>
                                                    @endunless
                                                </th>
                                                @foreach($types as $type)
                                                    @php
                                                        $cell = $option['entry']->measurements
                                                            ->first(fn ($m) => $m->measurement_type === $type && $m->basis === $basis);
                                                    @endphp
                                                    <td>{{ $cell && $cell->displayValue() ? $cell->displayValue() : '—' }}</td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endforeach
        @endforeach

        {{-- How-to-measure, matched to the basis of the figures beside it --}}
        <div class="card mb-4">
            <div class="card-header">How to measure</div>
            <div class="card-body">
                @foreach($bases as $basis)
                    @php $basisGuides = $guides->filter(fn ($g) => $g->basis === $basis); @endphp

                    <h6 class="{{ $basis === \App\Models\SizeChart::BASIS_BODY ? '' : 'mt-4' }}">
                        {{ $basis === \App\Models\SizeChart::BASIS_BODY ? 'Measuring your child' : 'Measuring a similar garment' }}
                    </h6>

                    @if($basisGuides->isEmpty())
                        <p class="text-muted small mb-0">Instructions for these measurements are being prepared.</p>
                    @else
                        <div class="row">
                            @foreach($basisGuides as $guide)
                                <div class="col-md-6 mb-3">
                                    <div class="fw-bold small">{{ $guide->name }}</div>
                                    <div class="small text-muted">{{ $guide->description }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @endif

    <div class="card mb-4">
        <div class="card-body small text-muted">
            <p class="mb-2"><strong>A note about measurements</strong></p>
            <p class="mb-0">
                If your child falls between two sizes, or the style is a particularly fitted one, send us your
                measurements in your request and our team will advise before we begin. Choosing the nearest size from
                the chart is always fine &mdash; it simply helps us to know you have checked.
            </p>
        </div>
    </div>

</div>
@endsection

@push('styles')
<style>
    /* Rows are all present in the page so the guide still reads without scripting.
       The script marks the guide as enhanced, and only then is the rest hidden. */
    .sg-hidden { display: none !important; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    var roots = document.querySelectorAll('[data-size-guide]');

    Array.prototype.forEach.call(roots, function (root) {
        var rows = Array.prototype.slice.call(root.querySelectorAll('[data-sg-row-band]'));
        if (!rows.length) return;

        var ageButtons = Array.prototype.slice.call(root.querySelectorAll('[data-sg-age-btn]'));
        var ageStep = root.querySelector('[data-sg-age-step]');

        function showRows(band) {
            // Every fitting offered for the chosen age band is shown together, so
            // the customer can compare them rather than being narrowed to one.
            rows.forEach(function (row) {
                var sameBand = row.getAttribute('data-sg-row-band') === String(band);
                row.classList.toggle('sg-hidden', !sameBand);
            });
        }

        function selectAge(band) {
            ageButtons.forEach(function (button) {
                var active = button.getAttribute('data-sg-age-btn') === String(band);
                button.classList.toggle('active', active);
                button.setAttribute('aria-pressed', active ? 'true' : 'false');
            });

            showRows(band);
        }

        ageButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                selectAge(button.getAttribute('data-sg-age-btn'));
            });
        });

        // The first band that actually has figures is the sensible starting point.
        var firstBand = ageButtons.filter(function (button) {
            return !button.disabled;
        })[0] || ageButtons[0];

        if (ageStep) ageStep.classList.remove('d-none');
        if (firstBand) selectAge(firstBand.getAttribute('data-sg-age-btn'));
    });
})();
</script>
@endpush