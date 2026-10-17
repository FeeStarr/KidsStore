@extends('layouts.admin', ['title' => 'New Size Chart'])
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">New Size Chart</h3>
    <a href="{{ route('admin.size-charts.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<div class="card">
    <div class="card-body">
        <form method="post" action="{{ route('admin.size-charts.store') }}">
            @csrf

            <div class="mb-3">
                <label class="form-label">Chart name</label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                       value="{{ old('name') }}" placeholder="e.g. Girls Frocks 2026" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label">Default measurement basis</label>
                <select name="default_basis" class="form-select @error('default_basis') is-invalid @enderror">
                    <option value="body" @selected(old('default_basis', 'body') === 'body')>
                        Body measurements &mdash; measure the child
                    </option>
                    <option value="finished_garment" @selected(old('default_basis') === 'finished_garment')>
                        Finished garment measurements &mdash; measure a similar laid-flat garment
                    </option>
                </select>
                <small class="form-text text-muted">
                    This is only a convenience for data entry. Each measurement row can override it,
                    and you can hold both datasets on the same chart.
                </small>
                @error('default_basis')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <button type="submit" class="btn btn-primary">Create chart</button>
            <a href="{{ route('admin.size-charts.index') }}" class="btn btn-link">Cancel</a>
        </form>
    </div>
</div>
@endsection