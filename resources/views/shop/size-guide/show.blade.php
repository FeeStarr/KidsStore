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
                    // Measurement types this chart actually holds for this basis.
                    $types = $chart->entries
                        ->flatMap(fn ($e) => $e->measurements->where('basis', $basis)->pluck('measurement_type'))
                        ->unique()->sort()->values();
                @endphp

                @continue($types->isEmpty())

                <div class="card mb-4">
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
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th style="min-width:130px">Size</th>
                                        @foreach($types as $type)
                                            <th class="small">{{ \App\Models\SizeChartMeasurement::where('measurement_type', $type)->value('measurement_type') ? ucwords(str_replace('_', ' ', $type)) : $type }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($chart->entries as $entry)
                                        <tr>
                                            <th scope="row">{{ $entry->label }}</th>
                                            @foreach($types as $type)
                                                @php
                                                    $cell = $entry->measurements
                                                        ->first(fn ($m) => $m->measurement_type === $type && $m->basis === $basis);
                                                @endphp
                                                <td>{{ $cell && $cell->displayValue() ? $cell->displayValue() : '—' }}</td>
                                            @endforeach
                                        </tr>
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