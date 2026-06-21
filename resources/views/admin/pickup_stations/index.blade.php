@extends('layouts.admin', ['title' => 'Pickup Stations'])
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Pickup Stations</h3>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.pickup-stations.create') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg"></i> Add Station
    </a>
        <form method="post" action="{{ route('admin.pickup-stations.apply-shipping-fee') }}" class="d-flex">
            @csrf
            <input type="number" step="0.01" min="0" name="fee" class="form-control form-control-sm" placeholder="Apply fee to all">
            <button class="btn btn-sm btn-outline-secondary ms-2">Apply</button>
        </form>
    </div>
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
                <th>Address</th>
                <th>City / State</th>
                <th>Phone</th>
                <th>Fee %</th>
                <th>Status</th>
                <th style="width:140px"></th>
            </tr>
        </thead>
        <tbody>
            @forelse($stations as $s)
                <tr>
                    <td class="fw-semibold">{{ $s->name }}</td>
                    <td>{{ $s->address }}</td>
                    <td>{{ collect([$s->city, $s->state])->filter()->implode(', ') ?: '—' }}</td>
                    <td>{{ $s->phone ?: '—' }}</td>
                    <td>{{ number_format($s->fee_pct, 2) }}%</td>
                    <td>
                        @if($s->is_active)
                            <span class="badge text-bg-success">Active</span>
                        @else
                            <span class="badge text-bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <a href="{{ route('admin.pickup-stations.payouts', $s) }}" class="btn btn-sm btn-outline-success" title="Payouts">
                            <i class="bi bi-cash-stack"></i>
                        </a>
                        <a href="{{ route('admin.pickup-stations.edit', $s) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form action="{{ route('admin.pickup-stations.destroy', $s) }}" method="post" class="d-inline"
                              data-confirm="Delete this pickup station?" data-confirm-title="Delete Station?"
                              data-confirm-yes="Yes, delete">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-muted text-center py-4">No pickup stations yet. <a href="{{ route('admin.pickup-stations.create') }}">Add one</a>.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
