@extends('layouts.admin', ['title' => 'Delivery Locations'])
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Delivery Locations</h3>
    <a href="{{ route('admin.delivery-locations.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg"></i> Add Location
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="card">
    <table class="table table-hover mb-0">
        <thead class="table-light">
            <tr>
                <th>Name</th>
                <th>State</th>
                <th>Description</th>
                <th>Charges</th>
                <th>Status</th>
                <th style="width:140px"></th>
            </tr>
        </thead>
        <tbody>
            @forelse($locations as $loc)
                <tr>
                    <td class="fw-semibold">{{ $loc->name }}</td>
                    <td>{{ $loc->state ?: '-' }}</td>
                    <td>{{ $loc->description ? Str::limit($loc->description, 50) : '-' }}</td>
                    <td><span class="badge bg-info">{{ $loc->charges_count }}</span></td>
                    <td>
                        @if($loc->is_active)
                            <span class="badge text-bg-success">Active</span>
                        @else
                            <span class="badge text-bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <a href="{{ route('admin.delivery-locations.edit', $loc) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form action="{{ route('admin.delivery-locations.destroy', $loc) }}" method="post" class="d-inline"
                              data-confirm="Delete this delivery location?" data-confirm-title="Delete Location?"
                              data-confirm-yes="Yes, delete">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-muted text-center py-4">No delivery locations yet. <a href="{{ route('admin.delivery-locations.create') }}">Add one</a>.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
