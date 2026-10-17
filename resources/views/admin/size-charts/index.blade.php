@extends('layouts.admin', ['title' => 'Size Charts'])
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Size Charts</h3>
    <a href="{{ route('admin.size-charts.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>New Chart
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="alert alert-info small">
    Charts ship with no measurements of their own. Add the figures your tailor supplies, then publish.
    A chart that is incomplete cannot be published, so a customer is never shown a blank or guessed size.
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table mb-0 align-middle">
            <thead>
                <tr>
                    <th>Chart</th>
                    <th>Version</th>
                    <th>Default basis</th>
                    <th class="text-center">Sizes</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($charts as $chart)
                    <tr>
                        <td>{{ $chart->name }}</td>
                        <td>v{{ $chart->version }}</td>
                        <td>{{ $chart->default_basis === 'body' ? 'Body' : 'Finished garment' }}</td>
                        <td class="text-center">{{ $chart->entries_count }}</td>
                        <td>
                            @if($chart->is_locked)
                                <span class="badge text-bg-secondary"><i class="bi bi-lock me-1"></i>Locked</span>
                            @elseif($chart->is_published && $chart->is_active)
                                <span class="badge text-bg-success">Published</span>
                            @elseif($chart->is_published)
                                <span class="badge text-bg-warning text-dark">Retired</span>
                            @else
                                <span class="badge text-bg-light text-dark">Draft</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.size-charts.show', $chart) }}" class="btn btn-sm btn-outline-primary">Manage</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            No size charts yet.
                            <a href="{{ route('admin.size-charts.create') }}">Create the first one</a>.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection