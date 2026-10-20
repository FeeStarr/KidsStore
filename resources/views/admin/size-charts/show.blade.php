@extends('layouts.admin', ['title' => $chart->name])
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0">{{ $chart->name }} <span class="badge text-bg-light text-dark">v{{ $chart->version }}</span></h3>
    </div>
    <a href="{{ route('admin.size-charts.index') }}" class="btn btn-outline-secondary btn-sm">All charts</a>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

@if($chart->is_locked)
    <div class="alert alert-secondary">
        <i class="bi bi-lock me-1"></i>
        This version is locked because an order was sized against it. Its figures must keep describing
        exactly what that customer saw.
        @if($chart->lockedByOrder)
            <a href="{{ route('admin.custom-orders.show', $chart->lockedByOrder) }}" class="ms-1">View that request</a>.
        @endif
        <form method="post" action="{{ route('admin.size-charts.new-version', $chart) }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-sm btn-primary ms-2">Create version {{ $chart->version + 1 }}</button>
        </form>
    </div>
@endif

{{-- Publication --}}
<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <strong>Status:</strong>
                @if($chart->is_published && $chart->is_active)
                    <span class="badge text-bg-success">Published</span>
                @elseif($chart->is_published)
                    <span class="badge text-bg-warning text-dark">Retired</span>
                @else
                    <span class="badge text-bg-light text-dark">Draft &mdash; not shown to customers</span>
                @endif
            </div>

            @unless($chart->is_locked)
                <div>
                    @if($chart->is_published && $chart->is_active)
                        <form method="post" action="{{ route('admin.size-charts.retire', $chart) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-secondary">Retire</button>
                        </form>
                    @else
                        <form method="post" action="{{ route('admin.size-charts.publish', $chart) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-success"
                                    @disabled($blockers !== [])>
                                <i class="bi bi-check-lg me-1"></i>Publish
                            </button>
                        </form>
                    @endif

                    <form method="post" action="{{ route('admin.size-charts.new-version', $chart) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-primary">
                            Create version {{ $chart->version + 1 }}
                        </button>
                    </form>
                </div>
            @endunless
        </div>

        @if($blockers !== [])
            <div class="alert alert-warning mt-3 mb-0 small">
                <strong>Not ready to publish:</strong>
                <ul class="mb-0 mt-1">
                    @foreach($blockers as $blocker)<li>{{ $blocker }}</li>@endforeach
                </ul>
            </div>
        @endif
    </div>
</div>

@unless($chart->is_locked)
    {{-- Chart settings --}}
    <div class="card mb-3">
        <div class="card-header">Chart settings</div>
        <div class="card-body">
            <form method="post" action="{{ route('admin.size-charts.update', $chart) }}" class="row g-3 align-items-end">
                @csrf @method('PUT')
                <div class="col-md-5">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" value="{{ $chart->name }}" required>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Default measurement basis</label>
                    <select name="default_basis" class="form-select">
                        @foreach($bases as $basis)
                            <option value="{{ $basis }}" @selected($chart->default_basis === $basis)>
                                {{ $basis === 'body' ? 'Body measurements' : 'Finished garment measurements' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Save</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Add a size --}}
    <div class="card mb-3">
        <div class="card-header">Add a size</div>
        <div class="card-body">
            <form method="post" action="{{ route('admin.size-charts.entries.store', $chart) }}" class="row g-3 align-items-end">
                @csrf
                <div class="col-md-4">
                    <label class="form-label">Label</label>
                    <input type="text" name="label" class="form-control" placeholder="e.g. 2-3 years" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Code <span class="text-muted">(optional)</span></label>
                    <input type="text" name="code" class="form-control" placeholder="e.g. 2-3Y">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fitting</label>
                    <select name="fit" class="form-select">
                        <option value="">{{ \App\Models\SizeChartEntry::FIT_UNASSIGNED_LABEL }}</option>
                        @foreach(\App\Models\SizeChart::FITS as $fit)
                            <option value="{{ $fit }}">{{ \App\Models\SizeChart::FIT_LABELS[$fit] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-success w-100"><i class="bi bi-plus-lg me-1"></i>Add size</button>
                </div>
            </form>
        </div>
    </div>
@endunless

{{-- Sizes and measurements --}}
@forelse($chart->entries as $entry)
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>
                <i class="bi bi-rulers me-1"></i>{{ $entry->label }}
                @if($entry->hasFit())
                    <span class="badge text-bg-secondary ms-1">{{ $entry->fitLabel() }}</span>
                @else
                    <span class="badge text-bg-light text-dark ms-1">{{ \App\Models\SizeChartEntry::FIT_UNASSIGNED_LABEL }}</span>
                @endif
                @if($entry->measurements->isEmpty())
                    <span class="badge text-bg-warning ms-1">No measurements yet</span>
                @endif
            </span>
            @unless($chart->is_locked)
                <form method="post" action="{{ route('admin.size-charts.entries.update', [$chart, $entry]) }}"
                      class="row g-2 align-items-end">
                    @csrf @method('PUT')
                    <div class="col-auto">
                        <input type="text" name="label" class="form-control form-control-sm" value="{{ $entry->label }}" required aria-label="Label">
                    </div>
                    <div class="col-auto">
                        <select name="fit" class="form-select form-select-sm" aria-label="Fitting">
                            <option value="">{{ \App\Models\SizeChartEntry::FIT_UNASSIGNED_LABEL }}</option>
                            @foreach(\App\Models\SizeChart::FITS as $fit)
                                <option value="{{ $fit }}" @selected($entry->fit === $fit)>
                                    {{ \App\Models\SizeChart::FIT_LABELS[$fit] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-auto">
                        <input type="text" name="code" class="form-control form-control-sm" value="{{ $entry->code }}"
                               placeholder="Code" aria-label="Code">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
                    </div>
                </form>
                <form method="post" action="{{ route('admin.size-charts.entries.destroy', [$chart, $entry]) }}"
                      onsubmit="return confirm('Remove {{ $entry->label }} and all its measurements?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger">Remove size</button>
                </form>
            @endunless
        </div>

        <div class="card-body">
            <table class="table table-sm mb-3 align-middle">
                <thead>
                    <tr>
                        <th>Measurement</th>
                        <th>Basis</th>
                        <th style="width:130px">Min</th>
                        <th style="width:130px">Max</th>
                        <th style="width:90px">Unit</th>
                        <th style="width:90px"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($entry->measurements->sortBy([['basis','asc'],['measurement_type','asc']]) as $measurement)
                        <tr>
                            <form method="post"
                                  action="{{ route('admin.size-charts.measurements.update', [$chart, $entry, $measurement]) }}">
                                @csrf @method('PUT')
                                <td>
                                    <select name="measurement_type" class="form-select form-select-sm">
                                        @foreach($measurementTypes as $field)
                                            <option value="{{ $field->name }}" @selected($measurement->measurement_type === $field->name)>
                                                {{ $field->label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <select name="basis" class="form-select form-select-sm">
                                        @foreach($bases as $basis)
                                            <option value="{{ $basis }}" @selected($measurement->basis === $basis)>
                                                {{ $basis === 'body' ? 'Body' : 'Finished garment' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="number" step="0.1" min="0" name="min_value" class="form-control form-control-sm"
                                           value="{{ $measurement->min_value }}">
                                </td>
                                <td>
                                    <input type="number" step="0.1" min="0" name="max_value" class="form-control form-control-sm"
                                           value="{{ $measurement->max_value }}">
                                </td>
                                <td>
                                    <input type="text" name="unit" class="form-control form-control-sm"
                                           value="{{ $measurement->unit }}">
                                </td>
                                <td class="text-end">
                                    <button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
                                </td>
                            </form>
                        </tr>
                        @if(! $chart->is_locked)
                        <tr>
                            <td colspan="6" class="border-0 pt-0">
                                <form method="post"
                                      action="{{ route('admin.size-charts.measurements.destroy', [$chart, $entry, $measurement]) }}"
                                      class="text-end" onsubmit="return confirm('Remove this measurement?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-link text-danger">Remove</button>
                                </form>
                            </td>
                        </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="6" class="text-muted small">
                                No measurements for this size yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @unless($chart->is_locked)
                <form method="post" action="{{ route('admin.size-charts.measurements.store', [$chart, $entry]) }}"
                      class="row g-2 align-items-end border-top pt-3">
                    @csrf
                    <div class="col-md-4">
                        <label class="form-label small text-muted">Add measurement</label>
                        <select name="measurement_type" class="form-select form-select-sm" required>
                            @foreach($measurementTypes as $field)
                                <option value="{{ $field->name }}">{{ $field->label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="basis" class="form-select form-select-sm">
                            @foreach($bases as $basis)
                                <option value="{{ $basis }}" @selected($chart->default_basis === $basis)>
                                    {{ $basis === 'body' ? 'Body' : 'Finished garment' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="number" step="0.1" min="0" name="min_value" class="form-control form-control-sm" placeholder="Min">
                    </div>
                    <div class="col-md-2">
                        <input type="number" step="0.1" min="0" name="max_value" class="form-control form-control-sm" placeholder="Max">
                    </div>
                    <div class="col-md-1">
                        <input type="text" name="unit" class="form-control form-control-sm" value="cm">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-sm btn-success w-100">Add</button>
                    </div>
                </form>
            @endunless
        </div>
    </div>
@empty
    <div class="card">
        <div class="card-body text-center text-muted py-4">
            No sizes yet. Add the first size above, then its measurements.
        </div>
    </div>
@endforelse

{{-- Fit coverage: which tiers each age band actually offers --}}
@if($chart->entries->isNotEmpty())
    <div class="card mb-3">
        <div class="card-header">Fitting coverage</div>
        <div class="card-body">
            <p class="text-muted small mb-2">
                A blank cell means that fitting is not offered for that age band, which is
                perfectly valid. A cell marked &ldquo;no figures&rdquo; is an offered fitting
                with no measurements behind it, and will block publishing.
            </p>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Age band</th>
                            <th>Standard</th>
                            @foreach(\App\Models\SizeChart::FITS as $fit)
                                <th>{{ \App\Models\SizeChart::FIT_LABELS[$fit] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($chart->fitCoverage() as $band => $cells)
                            <tr>
                                <td>{{ $band }}</td>
                                <td>
                                    @include('admin.size-charts.partials.coverage-cell', ['cell' => $cells['unassigned'] ?? null])
                                </td>
                                @foreach(\App\Models\SizeChart::FITS as $fit)
                                    <td>
                                        @include('admin.size-charts.partials.coverage-cell', ['cell' => $cells[$fit] ?? null])
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif

{{-- Global measurement instructions: shared by every chart --}}
<div class="card mb-3">
    <div class="card-header">Global measurement instructions</div>
    <div class="card-body">
        <div class="alert alert-warning">
            <strong>These instructions apply to all KidsFlairr size charts, not just this
            chart.</strong> Changes affect the instructions shown to customers across every
            chart that uses the relevant measurement types.
        </div>

        <p class="text-muted small">
            A check is not proof that a measurement technique is correct. These words tell
            customers how to measure, and getting one wrong produces a garment cut from bad
            figures. Please have them read before publishing.
        </p>

        @foreach($bases as $basis)
            <h6 class="mt-3">
                {{ $basis === \App\Models\SizeChart::BASIS_BODY ? 'Body measurements' : 'Finished garment measurements' }}
            </h6>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th style="width:180px">Measurement</th>
                            <th>Instructions</th>
                            <th style="width:90px">Shown</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($measurementTypes as $field)
                            @php($guide = $guides[$basis][$field->name] ?? null)
                            <tr>
                                <td class="small">{{ $field->label }}</td>
                                <td>
                                    @if($guide)
                                        <form method="post" action="{{ route('admin.size-charts.guides.update', [$chart, $guide]) }}">
                                            @csrf @method('PUT')
                                            <input type="text" name="name" class="form-control form-control-sm mb-1"
                                                   value="{{ $guide->name }}" required>
                                            <textarea name="description" rows="2" class="form-control form-control-sm mb-1" required>{{ $guide->description }}</textarea>
                                            @if(($guideUsage[$field->name] ?? []) !== [])
                                                <p class="text-muted small mb-1">
                                                    Also used by: {{ implode(', ', $guideUsage[$field->name]) }}
                                                </p>
                                            @endif
                                            <div class="form-check mb-1">
                                                <input class="form-check-input" type="checkbox" name="is_active" value="1"
                                                       id="active-{{ $basis }}-{{ $field->name }}" @checked($guide->is_active)>
                                                <label class="form-check-label small" for="active-{{ $basis }}-{{ $field->name }}">
                                                    Show to customers
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="confirm_global_change" value="1"
                                                       id="confirm-{{ $basis }}-{{ $field->name }}" required>
                                                <label class="form-check-label small" for="confirm-{{ $basis }}-{{ $field->name }}">
                                                    I understand this changes instructions on every chart using {{ $field->label }}
                                                </label>
                                            </div>
                                            <button type="submit" class="btn btn-sm btn-outline-primary mt-1">Save</button>
                                        </form>
                                    @else
                                        <span class="text-muted small">No instruction recorded for this basis.</span>
                                    @endif
                                </td>
                                <td class="small">
                                    @if($guide)
                                        <span class="badge text-bg-{{ $guide->is_active ? 'success' : 'secondary' }}">
                                            {{ $guide->is_active ? 'Active' : 'Hidden' }}
                                        </span>
                                    @else
                                        &mdash;
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    </div>
</div>
@endsection